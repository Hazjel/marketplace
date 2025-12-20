<?php

Namespace App\Interfaces;

use Illuminate\Support\Arr;
use App\Models\Transaction;

interface TransactionRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute);
    public function getAllPaginated(?string $search, ?int $rowPerPage);
    public function getTotalRevenue();
    public function getTotalAdminFee();
    public function getById(string $id);
    public function getByCode(string $code);
    public function create(array $data);
    public function updateStatus(string $id, array $data);
    public function delete(string $id);
    public function restoreStock(Transaction $transaction);
}
