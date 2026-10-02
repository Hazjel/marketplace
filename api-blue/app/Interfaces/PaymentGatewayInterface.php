<?php

namespace App\Interfaces;

use App\Models\Transaction;

interface PaymentGatewayInterface
{
    public const REFUND_DONE = 'refunded';

    public const REFUND_MANUAL = 'manual';

    /**
     * Minta Snap token buat transaksi. Return null kalau gateway gagal
     * (caller tidak boleh gagalkan request 500 karena order & stok sudah tercatat).
     */
    public function getSnapToken(Transaction $transaction): ?string;

    /**
     * Kembalikan seluruh grand_total ke pembeli lewat gateway.
     *
     * REFUND_DONE: gateway menerima refund. REFUND_MANUAL: metode bayarnya
     * tidak bisa direfund lewat API (VA bank, gerai), platform harus transfer
     * sendiri. Exception: gateway menolak atau tidak terjangkau, boleh dicoba lagi.
     */
    public function refund(Transaction $transaction, string $reason): string;
}
