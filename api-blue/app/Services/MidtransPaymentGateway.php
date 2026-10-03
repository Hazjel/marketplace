<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransTransaction;
use RuntimeException;

class MidtransPaymentGateway implements PaymentGatewayInterface
{
    // Metode yang bisa direfund lewat API Midtrans. VA bank dan gerai tidak:
    // https://docs.midtrans.com/docs/what-payment-method-that-have-refund-feature
    private const REFUNDABLE_TYPES = ['credit_card', 'gopay', 'shopeepay', 'qris', 'akulaku'];

    public function getSnapToken(Transaction $transaction): ?string
    {
        $this->configure();

        // A multi-store checkout is one payment for all of its orders.
        $params = [
            'transaction_details' => [
                'order_id' => $transaction->paymentCode(),
                'gross_amount' => (int) Transaction::inPayment($transaction->paymentCode())->sum('grand_total'),
            ],
            'customer_details' => [
                'first_name' => $transaction->buyer->user?->name ?? 'Customer',
                'email' => $transaction->buyer->user?->email ?? 'no-email@example.com',
            ],
            'callbacks' => [
                'finish' => env('FRONTEND_URL', 'http://localhost:5173').'/admin/transaction/'.$transaction->id,
            ],
            'expiry' => [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit' => 'minute',
                'duration' => 15,
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            Log::info('Snap token generated:', ['token' => $snapToken, 'transaction' => $transaction->code]);

            return $snapToken;
        } catch (\Throwable $e) {
            Log::error('Midtrans snap token failed: '.$e->getMessage(), [
                'transaction' => $transaction->code,
            ]);

            return null;
        }
    }

    public function refund(Transaction $transaction, string $reason): string
    {
        $this->configure();

        // Metode bayar hanya diketahui Midtrans: Snap membiarkan pembeli memilih.
        // Library mendeklarasikan array, tapi json_decode-nya mengembalikan objek.
        $orderId = $transaction->paymentCode();
        $status = (object) MidtransTransaction::status($orderId);
        $paymentType = $status->payment_type ?? null;
        $transactionStatus = $status->transaction_status ?? null;

        // Percobaan sebelumnya sudah berhasil tapi job mati sebelum mencatatnya.
        if (in_array($transactionStatus, ['refund', 'cancel'], true)) {
            return self::REFUND_DONE;
        }

        if (! in_array($paymentType, self::REFUNDABLE_TYPES, true)) {
            return self::REFUND_MANUAL;
        }

        // Kartu yang belum settle dibatalkan (void), bukan direfund. A void
        // cancels the whole payment, so an order sharing it with other stores
        // waits for settlement and is refunded partially instead; 414 is the
        // job's "not settled yet, retry later".
        if ($transactionStatus === 'capture') {
            if ($transaction->payment_code !== null) {
                throw new RuntimeException('Pembayaran gabungan belum settle; refund sebagian menunggu settlement.', 414);
            }
            MidtransTransaction::cancel($orderId);

            return self::REFUND_DONE;
        }

        // Only this order's amount: other orders in a shared payment stay paid.
        MidtransTransaction::refund($orderId, [
            'refund_key' => 'cancel-'.$transaction->code,
            'amount' => (int) $transaction->grand_total,
            'reason' => mb_substr($reason, 0, 255),
        ]);

        return self::REFUND_DONE;
    }

    private function configure(): void
    {
        Config::$serverKey = config('midtrans.serverKey');
        Config::$isProduction = config('midtrans.isProduction');
        Config::$isSanitized = config('midtrans.isSanitized');
        Config::$is3ds = config('midtrans.is3ds');
    }
}
