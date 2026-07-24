<?php

namespace App\Services;

class MidtransPaymentStatusInterpreter
{
    /**
     * Map status Midtrans (webhook callback maupun manual poll) ke payment_status internal.
     * Satu-satunya tempat aturan ini didefinisikan — webhook dan manual poll harus selalu sinkron.
     *
     * Null berarti "no-op" — status tidak berubah (mis. capture non-credit_card,
     * yang aslinya Midtrans hanya kirim buat metode pembayaran lain yang tidak butuh aksi).
     */
    public static function interpret(?string $transactionStatus, ?string $paymentType, ?string $fraudStatus): ?string
    {
        return match ($transactionStatus) {
            'capture' => $paymentType === 'credit_card'
                ? ($fraudStatus === 'challenge' ? 'unpaid' : 'paid')
                : null,
            'settlement' => 'paid',
            'pending' => 'unpaid',
            'deny', 'expire', 'cancel' => 'failed',
            default => 'failed',
        };
    }
}
