<?php

namespace App\Listeners;

use App\Events\TransactionStatusUpdated;
use App\Models\Transaction;
use App\Services\PushNotificationService;

/**
 * Auto-discovered by Laravel (handle() type-hints the event — no explicit
 * EventServiceProvider registration needed, matches this app's convention
 * of not having one). Fires a push alongside the existing Reverb broadcast
 * every time TransactionStatusUpdated is dispatched, so the buyer gets
 * notified even when the app isn't open to receive the websocket event.
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

        $this->push->sendToUser(
            $user,
            'Pesanan '.$transaction->code,
            $this->statusMessage($transaction),
            ['type' => 'transaction', 'transaction_id' => (string) $transaction->id],
        );
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
