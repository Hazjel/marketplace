<?php

namespace App\Support;

use App\Models\Buyer;
use App\Models\Complaint;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ComplaintNotification;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Email + push to whoever has to know about a complaint's new status. Called
 * by TransactionRepository after the change is committed. An approval
 * reaches the buyer through the refund push/email
 * (SendPushOnTransactionStatusUpdated), so only the seller is told here.
 */
class ComplaintAlerts
{
    private const REASONS = [
        'not_received' => 'Barang tidak sampai',
        'damaged' => 'Barang rusak',
        'wrong_item' => 'Barang salah',
        'other' => 'Lainnya',
    ];

    public static function send(Transaction $transaction): void
    {
        try {
            $transaction->loadMissing('complaint');
            $complaint = $transaction->complaint;
            // ponytail: once per (complaint, status) via cache; a cache flush can resend once.
            if (! $complaint || ! Cache::add("complaint-alert:{$complaint->id}:{$complaint->status}", true, now()->addDays(30))) {
                return;
            }

            foreach (self::messages($transaction, $complaint) as [$user, $subject, $lines, $action, $url]) {
                self::deliver($user, $transaction, $subject, $lines, $action, $url);
            }
        } catch (Throwable $e) {
            Log::error('Notifikasi komplain gagal', ['transaction' => $transaction->code, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return list<array{User, string, list<string>, string, string}>
     */
    private static function messages(Transaction $transaction, Complaint $complaint): array
    {
        $code = $transaction->code;
        $buyer = User::find(Buyer::whereKey($transaction->buyer_id)->value('user_id'));
        $seller = User::find(Store::whereKey($transaction->store_id)->value('user_id'));
        $storefront = config('marketplace.storefront_url');
        $sellerUrl = $storefront.'/admin/transaction/'.$transaction->id;
        $buyerUrl = $storefront.'/'.rawurlencode((string) $buyer?->username).'/transaction/'.$transaction->id;
        $adminNote = 'Catatan admin: '.self::plain((string) $complaint->admin_note);

        $toSeller = fn (string $subject, array $lines, string $action = 'Lihat Pesanan') => $seller ? [[$seller, $subject, $lines, $action, $sellerUrl]] : [];
        $toBuyer = fn (string $subject, array $lines) => $buyer ? [[$buyer, $subject, $lines, 'Lihat Pesanan', $buyerUrl]] : [];

        return match ($complaint->status) {
            'open' => $toSeller("Komplain baru untuk pesanan {$code}", [
                "Pembeli mengajukan komplain untuk pesanan **{$code}**.",
                'Alasan: '.(self::REASONS[$complaint->reason] ?? $complaint->reason),
                'Keterangan: "'.self::plain(Str::limit((string) $complaint->description, 200)).'"',
                'Tanggapi sebelum '.$complaint->deadline_at->format('d/m/Y H:i').' WIB, setelah itu komplain diteruskan ke admin.',
            ], 'Tanggapi Komplain'),
            'escalated' => $toBuyer("Komplain pesanan {$code} sedang ditinjau admin", [
                "Komplainmu untuk pesanan **{$code}** sedang ditinjau admin.",
                $complaint->seller_response === null
                    ? 'Penjual tidak menanggapi dalam 2 hari.'
                    : 'Penjual menolak komplain: "'.self::plain($complaint->seller_response).'"',
                'Kami kabari lagi setelah admin memutuskan.',
            ]),
            'rejected' => [
                ...$toBuyer("Komplain pesanan {$code} ditolak", [
                    "Komplainmu untuk pesanan **{$code}** ditolak admin.",
                    $adminNote,
                    'Pesanan berlanjut. Konfirmasi penerimaan setelah barang sampai.',
                ]),
                ...$toSeller("Komplain pesanan {$code} ditolak", [
                    "Komplain pembeli untuk pesanan **{$code}** ditolak admin, pesanan berlanjut.",
                    $adminNote,
                ]),
            ],
            'withdrawn' => $toSeller("Komplain pesanan {$code} ditarik", [
                "Pembeli menarik komplain untuk pesanan **{$code}**, pesanan berlanjut.",
            ]),
            // A seller who accepted it themselves already knows.
            'approved' => $complaint->escalated_at === null ? [] : $toSeller("Komplain pesanan {$code} disetujui", [
                "Komplain pembeli untuk pesanan **{$code}** disetujui admin. Dana dikembalikan penuh ke pembeli, barang tidak perlu dikembalikan.",
                $adminNote,
            ]),
            default => [],
        };
    }

    /**
     * @param  list<string>  $lines
     */
    private static function deliver(User $user, Transaction $transaction, string $subject, array $lines, string $action, string $url): void
    {
        try {
            $user->notify(new ComplaintNotification($subject, $lines, $action, $url));
            app(PushNotificationService::class)->sendToUser(
                $user,
                $subject,
                str_replace('**', '', $lines[0]),
                ['type' => 'transaction', 'transaction_id' => (string) $transaction->id],
            );
        } catch (Throwable $e) {
            // The complaint change is committed; a lost message must not fail it.
            Log::error('Notifikasi komplain gagal', ['transaction' => $transaction->code, 'user' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /** User-written text inside a Markdown mail: no links or images. */
    private static function plain(string $text): string
    {
        return str_replace(['[', ']'], ['\[', '\]'], $text);
    }
}
