<?php

namespace App\Interfaces;

use App\Models\Transaction;

interface TransactionRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute);

    public function getAllPaginated(?string $search, ?int $rowPerPage);

    public function getTotalRevenue(?string $mode = null);

    public function getTotalCount(): int;

    public function getTotalAdminFee(?string $mode = null);

    public function getChartData(int $days = 7, ?string $mode = null);

    public function getStatusBreakdown(?string $mode = null);

    public function getWeekOverWeekTrend(): ?array;

    public function getById(string $id);

    public function getByCode(string $code);

    public function create(array $data);

    public function updateStatus(string $id, array $data);

    public function delete(string $id);

    public function restoreStock(Transaction $transaction);
}
