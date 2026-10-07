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
     * @return Collection<int, Transaction>
     */
    public function checkout(array $orders);

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
