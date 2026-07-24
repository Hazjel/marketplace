<?php

namespace App\Interfaces;

interface TransactionAnalyticsRepositoryInterface
{
    public function getTotalRevenue(?string $mode = null);

    public function getTotalCount(): int;

    public function getTotalAdminFee(?string $mode = null);

    public function getChartData(int $days = 7, ?string $mode = null);

    public function getStatusBreakdown(?string $mode = null);

    public function getWeekOverWeekTrend(): ?array;
}
