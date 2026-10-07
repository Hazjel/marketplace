<?php

namespace App\Interfaces;

use App\Models\Transaction;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute);

    public function getAllPaginated(?string $search, ?int $rowPerPage);

    public function getById(string $id);

    public function getByCode(string $code);

    public function create(array $data);

    /**
     * @param  list<array<string, mixed>>  $orders  one per store, paid together
     * @param  bool  $useBalance  pay what Saldo Blukios covers first
     * @return Collection<int, Transaction>
     */
    public function checkout(array $orders, bool $useBalance = false);

    /**
     * The payment arrived (webhook, manual check, or Saldo Blukios covered it
     * all). Caller holds the transaction's row lock and its DB transaction.
     */
    public function markPaid(Transaction $transaction): void;

    /**
     * An unpaid order failed or was cancelled: stock and its Saldo Blukios
     * part go back, each exactly once. Caller holds the row lock and its DB
     * transaction, and compensates $mongoAdjustments on rollback.
     */
    public function markFailed(Transaction $transaction, array &$mongoAdjustments): void;

    /**
     * markFailed() for every order of one payment, locked by the caller:
     * all stock first, then all balance returns (buyer lock last).
     *
     * @param  iterable<Transaction>  $transactions
     */
    public function failPayment(iterable $transactions, array &$mongoAdjustments): void;

    /**
     * Money arrived for an order that already failed (stock and balance are
     * back): its Midtrans part becomes a refund. Caller holds the lock and
     * calls startRefund() after commit. False when nothing is owed or a
     * refund already exists.
     */
    public function refundLatePayment(Transaction $transaction): bool;

    /**
     * Dispatches RefundCancelledTransactionJob for a committed refund_status
     * "processing", falling back to Saldo Blukios if it cannot be queued.
     */
    public function startRefund(Transaction $transaction): void;

    public function updateStatus(string $id, array $data);

    public function delete(string $id);

    public function restoreStock(Transaction $transaction, array &$mongoAdjustments);

    public function compensateStockRestoreRollback(array $mongoAdjustments);

    public function completeTransaction(string $id, ?string $receivingProof = null): Transaction;

    public function cancelPaidOrder(string $id, string $reason): Transaction;

    public function saveRefundAccount(string $id, array $account): Transaction;

    public function markRefundTransferred(string $id, string $note): Transaction;

    public function refundToBalance(string $id, string $note, string $from = 'processing'): ?Transaction;
}
