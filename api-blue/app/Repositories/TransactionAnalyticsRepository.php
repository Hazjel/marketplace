<?php

namespace App\Repositories;

use App\Interfaces\TransactionAnalyticsRepositoryInterface;
use App\Models\Transaction;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class TransactionAnalyticsRepository implements TransactionAnalyticsRepositoryInterface
{
    /**
     * Scope query ke store_id/buyer_id milik user login, berdasar $mode eksplisit.
     * Wajib dipakai (bukan cek hasRole berurutan) karena user bisa dual-role
     * (buyer + store sekaligus, sejak dukung mode ganda ala Shopee) — hasRole
     * saja tak cukup untuk tahu KONTEKS mana yang diminta caller.
     * Return false kalau user tidak punya akses ke mode yang diminta.
     */
    private function scopeToMode($query, ?string $mode): bool
    {
        if (! auth()->check()) {
            return false;
        }
        $user = auth()->user();

        if ($mode === 'store') {
            if (! $user->hasRole('store') || ! $user->store) {
                return false;
            }
            $query->where('store_id', $user->store->id);

            return true;
        }

        if ($mode === 'buyer') {
            if (! $user->hasRole('buyer') || ! $user->buyer) {
                return false;
            }
            $query->where('buyer_id', $user->buyer->id);

            return true;
        }

        // Tanpa mode eksplisit: admin lihat semua, non-admin default ke
        // scope store (prioritas) lalu buyer — pola lama dipertahankan
        // untuk pemanggil yang belum di-migrasi ke $mode eksplisit.
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->hasRole('store') && $user->store) {
            $query->where('store_id', $user->store->id);

            return true;
        }
        if ($user->hasRole('buyer') && $user->buyer) {
            $query->where('buyer_id', $user->buyer->id);

            return true;
        }

        return false;
    }

    public function getTotalRevenue(?string $mode = null)
    {
        $query = Transaction::where('payment_status', 'paid');

        if (! $this->scopeToMode($query, $mode)) {
            return 0;
        }

        return $query->sum('grand_total');
    }

    public function getTotalCount(): int
    {
        return Transaction::count();
    }

    public function getTotalAdminFee(?string $mode = null)
    {
        $query = Transaction::where('payment_status', 'paid');

        if (! $this->scopeToMode($query, $mode)) {
            return 0;
        }

        return $query->sum('admin_fee');
    }

    /**
     * Time-series revenue (store) atau pengeluaran (buyer) N hari terakhir.
     * $days dibatasi allow-list oleh controller (7/30/90).
     */
    public function getChartData(int $days = 7, ?string $mode = null)
    {
        $query = Transaction::query()->where('payment_status', 'paid');

        if (! $this->scopeToMode($query, $mode)) {
            return [];
        }

        $endDate = now('Asia/Jakarta')->endOfDay();
        $startDate = now('Asia/Jakarta')->subDays($days - 1)->startOfDay();
        $period = CarbonPeriod::create($startDate, $endDate);

        $transactions = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(grand_total) as total_revenue'),
                DB::raw('COUNT(*) as total_transaction')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        // Fill missing dates with 0
        $data = [];
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $record = $transactions->firstWhere('date', $dateString);

            $data[] = [
                'date' => $dateString,
                'total_revenue' => $record ? (int) $record->total_revenue : 0,
                'total_transaction' => $record ? (int) $record->total_transaction : 0,
            ];
        }

        return $data;
    }

    /**
     * Hitung transaksi per status (payment_status + delivery_status) untuk
     * user login, role-scoped. Single query pakai conditional SUM.
     */
    public function getStatusBreakdown(?string $mode = null)
    {
        $query = Transaction::query();

        if (! $this->scopeToMode($query, $mode)) {
            return [
                'unpaid' => 0, 'paid' => 0, 'failed' => 0,
                'pending' => 0, 'shipping' => 0, 'delivering' => 0,
                'delivered' => 0, 'completed' => 0, 'cancelled' => 0,
            ];
        }

        $row = $query->selectRaw("
            SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN delivery_status = 'shipping' THEN 1 ELSE 0 END) as shipping,
            SUM(CASE WHEN delivery_status = 'delivering' THEN 1 ELSE 0 END) as delivering,
            SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN delivery_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN delivery_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        ")->first();

        return [
            'unpaid' => (int) $row->unpaid,
            'paid' => (int) $row->paid,
            'failed' => (int) $row->failed,
            'pending' => (int) $row->pending,
            'shipping' => (int) $row->shipping,
            'delivering' => (int) $row->delivering,
            'delivered' => (int) $row->delivered,
            'completed' => (int) $row->completed,
            'cancelled' => (int) $row->cancelled,
        ];
    }

    /**
     * Bandingkan revenue & jumlah order 7 hari terakhir vs 7 hari sebelumnya
     * (store-only — dipakai untuk trend badge di dashboard seller).
     * Return null kalau bukan store (tidak ada perbandingan bermakna).
     */
    public function getWeekOverWeekTrend(): ?array
    {
        if (! auth()->check() || ! auth()->user()->hasRole('store')) {
            return null;
        }

        $storeId = auth()->user()->store?->id;
        if (! $storeId) {
            return null;
        }

        $now = now('Asia/Jakarta');
        $thisWeekStart = $now->copy()->subDays(6)->startOfDay();
        $lastWeekStart = $now->copy()->subDays(13)->startOfDay();
        $lastWeekEnd = $now->copy()->subDays(7)->endOfDay();

        $base = Transaction::where('store_id', $storeId)->where('payment_status', 'paid');

        $thisWeek = (clone $base)->whereBetween('created_at', [$thisWeekStart, $now])
            ->selectRaw('COALESCE(SUM(grand_total), 0) as revenue, COUNT(*) as orders')
            ->first();

        $lastWeek = (clone $base)->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->selectRaw('COALESCE(SUM(grand_total), 0) as revenue, COUNT(*) as orders')
            ->first();

        return [
            'revenue' => $this->percentChange((float) $thisWeek->revenue, (float) $lastWeek->revenue),
            'orders' => $this->percentChange((int) $thisWeek->orders, (int) $lastWeek->orders),
        ];
    }

    /**
     * Persentase perubahan; null kalau baseline 0 (tidak ada dasar perbandingan
     * yang bermakna — FE harus sembunyikan badge trend, bukan tampilkan Infinity).
     */
    private function percentChange(float $current, float $previous): ?array
    {
        if ($previous <= 0) {
            return null;
        }

        $percent = (($current - $previous) / $previous) * 100;

        return [
            'value' => round(abs($percent), 1),
            'direction' => $percent >= 0 ? 'up' : 'down',
        ];
    }
}
