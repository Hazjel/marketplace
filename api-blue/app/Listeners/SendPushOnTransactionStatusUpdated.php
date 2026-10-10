<?php

namespace App\Listeners;

use App\Events\TransactionStatusUpdated;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\RefundStatusNotification;
use App\Services\PushNotificationService;
use App\Support\RefundMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-discovered by Laravel (handle() type-hints the event — no explicit
 * EventServiceProvider registration needed, matches this app's convention
 * of not having one). Fires a push alongside the existing Reverb broadcast
 * every time TransactionStatusUpdated is dispatched, so the buyer gets
 * notified even when the app isn't open to receive the websocket event.
 * Once the order has a refund, the push says what happened to the money,
 * and a final refund state (refunded, manual_required) is also emailed,
 * since web buyers get no push.
 *
 * Buyer-only for now — sellers don't get a push when their own delivery
 * status update fires this event back at them (they already know, they
 * just made the change).
 */
class SendPushOnTransactionStatusUpdated
{
    public function __construct(private PushNotificationService $push) {}

    public function handle(TransactionStatusUpdated $event): void
    {
        $transaction = $event->transaction->loadMissing('buyer.user');
        $user = $transaction->buyer?->user;
        if (! $user) {
            return;
        }

        $refundMessage = RefundMessage::for($transaction);

        $this->push->sendToUser(
            $user,
            'Pesanan '.$transaction->code,
            $refundMessage ?? $this->statusMessage($transaction),
            ['type' => 'transaction', 'transaction_id' => (string) $transaction->id],
        );

        if ($refundMessage !== null && in_array($transaction->refund_status, ['refunded', 'manual_required'], true)) {
            $this->mailRefund($user, $transaction, $refundMessage);
        }
    }

    private function mailRefund(User $user, Transaction $transaction, string $message): void
    {
        try {
            // ponytail: once per (order, status) via cache; a cache flush can resend once.
            if (Cache::add("refund-mail:{$transaction->id}:{$transaction->refund_status}", true, now()->addDays(30))) {
                $user->notify(new RefundStatusNotification((string) $transaction->id, $transaction->code, $message));
            }
        } catch (Throwable $e) {
            // The refund itself is done; a lost email must not fail the request or job.
            Log::error('Email refund gagal dikirim', ['transaction' => $transaction->code, 'error' => $e->getMessage()]);
        }
    }

    private function statusMessage(Transaction $transaction): string
    {
        return match ($transaction->delivery_status) {
            'processing' => 'Pesananmu sedang diproses penjual.',
            'delivering' => 'Pesananmu sedang dikirim.',
            'completed' => 'Pesananmu sudah selesai. Terima kasih!',
            'cancelled' => 'Pesananmu dibatalkan.',
            'failed' => 'Pengiriman pesananmu gagal.',
            default => match ($transaction->payment_status) {
                'paid' => 'Pembayaran berhasil, pesanan sedang diproses.',
                'failed' => 'Pembayaran gagal.',
                'expired' => 'Waktu pembayaran habis.',
                default => 'Status pesananmu berubah.',
            },
        };
    }
}
