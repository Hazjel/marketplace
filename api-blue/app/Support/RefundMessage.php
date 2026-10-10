<?php

namespace App\Support;

use App\Models\Transaction;
use App\ValueObjects\Money;

/**
 * What the buyer is told about their money once an order has a refund_status
 * (push body and refund email). refund_amount is the Midtrans part only; the
 * balance_used part went back to Saldo Blukios when the order was cancelled.
 */
class RefundMessage
{
    public static function for(Transaction $transaction): ?string
    {
        if ($transaction->refund_status === null) {
            return null;
        }

        $refund = Money::fromDecimalString((string) ($transaction->refund_amount ?? 0));
        $balance = Money::fromDecimalString((string) ($transaction->balance_used ?? 0));
        $x = self::rupiah($refund);
        $balanceNote = $balance->isZero() ? '' : ' '.self::rupiah($balance).' sudah kembali ke Saldo Blukios.';

        if ($transaction->refund_status === 'refunded' && $transaction->refund_method === 'balance') {
            return self::rupiah($refund->add($balance)).' sudah masuk ke Saldo Blukios dan bisa dipakai untuk belanja.';
        }

        if ($refund->isZero()) {
            // Nothing owed through Midtrans (paid fully with Saldo Blukios).
            return $balance->isZero() ? null : 'Pesananmu dibatalkan.'.$balanceNote;
        }

        return match ($transaction->refund_status) {
            'processing' => "Pesananmu dibatalkan. Pengembalian {$x} ke metode pembayaranmu sedang diproses.".$balanceNote,
            'manual_required' => "Pengembalian dana {$x} sedang kami periksa dan akan segera diselesaikan.".$balanceNote,
            'refunded' => match ($transaction->refund_method) {
                'manual' => "{$x} sudah ditransfer ke rekening yang kamu isi.".$balanceNote,
                default => "{$x} sudah dikembalikan ke metode pembayaranmu. Waktu dana masuk mengikuti bank atau penyedia e-wallet.".$balanceNote,
            },
            default => null,
        };
    }

    private static function rupiah(Money $amount): string
    {
        return 'Rp'.number_format($amount->minor(), 0, ',', '.');
    }
}
