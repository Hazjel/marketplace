<?php

namespace Tests\Support;

use App\Interfaces\EscrowRepositoryInterface;
use App\Models\Transaction;

/**
 * EscrowRepositoryInterface fake yang meledak di refund() -- dipakai untuk
 * mensimulasikan operasi SETELAH restoreStock() yang gagal di dalam
 * transaksi outer yang sama (mis. TransactionRepository::updateStatus()),
 * tanpa perlu memanipulasi state store balance supaya refund asli gagal
 * secara alami.
 */
class ExplodingRefundEscrowRepository implements EscrowRepositoryInterface
{
    public function credit(Transaction $transaction): void {}

    public function release(Transaction $transaction): void {}

    public function refund(Transaction $transaction): void
    {
        throw new \RuntimeException('refund sengaja gagal untuk test kompensasi outer-transaction');
    }
}
