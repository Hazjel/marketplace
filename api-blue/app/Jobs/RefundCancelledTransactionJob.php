<?php

namespace App\Jobs;

use App\Events\TransactionStatusUpdated;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengembalikan uang pembeli lewat Midtrans setelah penjual membatalkan
 * pesanan yang sudah dibayar. Metode yang tidak bisa direfund lewat API, dan
 * refund yang tetap gagal setelah semua percobaan, jatuh ke manual_required:
 * platform transfer sendiri ke rekening yang diisi pembeli.
 */
class RefundCancelledTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(private readonly string $transactionId) {}

    public function handle(PaymentGatewayInterface $gateway): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (! $transaction || $transaction->refund_status !== 'processing') {
            return;
        }

        $result = $gateway->refund($transaction, $transaction->refund_reason ?? 'Pesanan dibatalkan penjual');

        if ($result === PaymentGatewayInterface::REFUND_DONE) {
            $transaction->update([
                'refund_status' => 'refunded',
                'refund_method' => 'midtrans',
                'refunded_at' => now(),
            ]);
        } else {
            $transaction->update([
                'refund_status' => 'manual_required',
                'refund_method' => 'manual',
                'refund_note' => 'Metode pembayaran tidak mendukung refund otomatis',
            ]);
        }

        Log::info('Refund pesanan dibatalkan', [
            'transaction' => $transaction->code,
            'refund_status' => $transaction->refund_status,
        ]);

        event(new TransactionStatusUpdated($transaction->fresh()));
    }

    public function failed(Throwable $e): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (! $transaction || $transaction->refund_status !== 'processing') {
            return;
        }

        $transaction->update([
            'refund_status' => 'manual_required',
            'refund_method' => 'manual',
            'refund_note' => 'Refund otomatis gagal: '.mb_substr($e->getMessage(), 0, 500),
        ]);

        Log::error('Refund otomatis gagal, dialihkan ke manual', [
            'transaction' => $transaction->code,
            'error' => $e->getMessage(),
        ]);

        event(new TransactionStatusUpdated($transaction->fresh()));
    }
}
