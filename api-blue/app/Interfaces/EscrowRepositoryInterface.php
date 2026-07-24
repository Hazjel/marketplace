<?php

namespace App\Interfaces;

use App\Models\Transaction;

interface EscrowRepositoryInterface
{
    /**
     * Credit ke pending_balance (ESCROW) saat pembayaran diterima.
     */
    public function credit(Transaction $transaction): void;

    /**
     * Rilis dana dari pending_balance ke available balance saat pesanan selesai.
     */
    public function release(Transaction $transaction): void;

    /**
     * Refund/batalkan escrow saat transaksi gagal/dibatalkan.
     */
    public function refund(Transaction $transaction): void;
}
