<?php

namespace App\Interfaces;

use App\Models\Transaction;

interface PaymentGatewayInterface
{
    /**
     * Minta Snap token buat transaksi. Return null kalau gateway gagal
     * (caller tidak boleh gagalkan request 500 karena order & stok sudah tercatat).
     */
    public function getSnapToken(Transaction $transaction): ?string;
}
