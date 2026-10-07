<?php

namespace App\Jobs;

use App\Events\TransactionStatusUpdated;
use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use App\Support\BusinessMetrics;
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
 * refund yang tetap gagal setelah semua percobaan, dikembalikan ke Saldo
 * Blukios (TransactionRepository::refundToBalance).
 */
class RefundCancelledTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Refund QRIS/e-wallet butuh dana yang sudah cair di saldo merchant:
    // Midtrans menjawab 414 "insufficient funds" sampai pembayaran settle
    // (satu-dua hari). Terus coba selama itu sebelum dialihkan ke Saldo Blukios.
    // Setelah 21600 jeda terakhir berulang: 12 percobaan ~1,8 hari.
    public int $tries = 12;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 1800, 3600, 21600];

    // Kode Midtrans yang layak dicoba lagi; 4xx lain tidak akan berubah.
    private const RETRYABLE_CODES = [414, 429];

    public function __construct(private readonly string $transactionId) {}

    public function handle(PaymentGatewayInterface $gateway): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (! $transaction || $transaction->refund_status !== 'processing') {
            return;
        }

        try {
            $result = $gateway->refund($transaction, $transaction->refund_reason ?? 'Pesanan dibatalkan penjual');
        } catch (Throwable $e) {
            if ($this->isRetryable($e)) {
                throw $e;
            }

            $this->fallBackToBalance($transaction, $e);

            return;
        }

        if ($result === PaymentGatewayInterface::REFUND_DONE) {
            $transaction->update([
                'refund_status' => 'refunded',
                'refund_method' => 'midtrans',
                'refunded_at' => now(),
            ]);
            BusinessMetrics::record('refund_done', 'midtrans');
        } else {
            app(TransactionRepositoryInterface::class)->refundToBalance($transaction->id, 'Metode pembayaran tidak mendukung refund lewat Midtrans');
        }

        Log::info('Refund pesanan dibatalkan', [
            'transaction' => $transaction->code,
            'refund_status' => $transaction->fresh()?->refund_status,
        ]);

        event(new TransactionStatusUpdated($transaction->fresh()));
    }

    public function failed(Throwable $e): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (! $transaction || $transaction->refund_status !== 'processing') {
            return;
        }

        try {
            // A timeout or 5xx may have reached Midtrans anyway: crediting the
            // balance too could pay the buyer twice, so an admin checks first.
            if ($this->isAmbiguous($e)) {
                $this->leaveForAdmin($transaction, $e);
            } else {
                $this->fallBackToBalance($transaction, $e);
            }
        } catch (Throwable $fallbackError) {
            // Stays "processing": ops:check's stuck-refund alert reaches an admin.
            Log::error('Refund macet', [
                'transaction' => $transaction->code,
                'error' => $e->getMessage(),
                'fallback_error' => $fallbackError->getMessage(),
            ]);
        }
    }

    private function leaveForAdmin(Transaction $transaction, Throwable $e): void
    {
        $updated = Transaction::where('id', $transaction->id)
            ->where('refund_status', 'processing')
            ->update([
                'refund_status' => 'manual_required',
                'refund_method' => 'manual',
                'refund_note' => 'Refund otomatis tidak terkonfirmasi setelah semua percobaan — cek dashboard Midtrans dulu; kalau belum direfund, Kembalikan ke Saldo Blukios',
            ]);

        if ($updated === 0) {
            return;
        }

        BusinessMetrics::record('refund_manual_required', 'unconfirmed');
        Log::error('Refund otomatis tidak terkonfirmasi, menunggu admin', [
            'transaction' => $transaction->code,
            'error' => $e->getMessage(),
        ]);

        event(new TransactionStatusUpdated($transaction->fresh()));
    }

    // 414 is a definite "funds never settled", unlike these.
    private function isAmbiguous(Throwable $e): bool
    {
        $code = (int) $e->getCode();

        return $code === 0 || $code >= 500 || $code === 429;
    }

    // Gangguan jaringan (kode 0) dan 5xx Midtrans juga sementara.
    private function isRetryable(Throwable $e): bool
    {
        $code = (int) $e->getCode();

        return $code === 0 || $code >= 500 || in_array($code, self::RETRYABLE_CODES, true);
    }

    private function fallBackToBalance(Transaction $transaction, Throwable $e): void
    {
        $refunded = app(TransactionRepositoryInterface::class)->refundToBalance(
            $transaction->id,
            'Refund otomatis gagal: '.mb_substr($e->getMessage(), 0, 500),
        );

        if (! $refunded) {
            return;
        }

        Log::error('Refund otomatis gagal, dialihkan ke Saldo Blukios', [
            'transaction' => $transaction->code,
            'error' => $e->getMessage(),
        ]);

        event(new TransactionStatusUpdated($refunded));
    }
}
