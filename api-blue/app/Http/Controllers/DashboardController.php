<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\AdminDashboardResource;
use App\Http\Resources\BuyerDashboardResource;
use App\Http\Resources\SellerDashboardResource;
use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\ProductReviewRepositoryInterface;
use App\Interfaces\StoreBalanceRepositoryInterface;
use App\Interfaces\StoreRepositoryInterface;
use App\Interfaces\TransactionAnalyticsRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private TransactionAnalyticsRepositoryInterface $transactionAnalyticsRepository,
        private ProductRepositoryInterface $productRepository,
        private ProductReviewRepositoryInterface $productReviewRepository,
        private StoreBalanceRepositoryInterface $storeBalanceRepository,
        private StoreRepositoryInterface $storeRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    private function resolveDays(Request $request): int
    {
        $days = (int) $request->query('days', 7);

        return in_array($days, [7, 30, 90], true) ? $days : 7;
    }

    public function sellerSummary(Request $request)
    {
        if (! auth()->user()->hasRole('store')) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
        }

        $storeId = auth()->user()->store?->id;
        if (! $storeId) {
            return ResponseHelper::jsonResponse(false, 'Toko tidak ditemukan', null, 404);
        }

        try {
            $balance = $this->storeBalanceRepository->getByStore();
            $statusBreakdown = $this->transactionAnalyticsRepository->getStatusBreakdown('store');

            $data = [
                'balance' => $balance?->balance ?? 0,
                'pending_balance' => $balance?->pending_balance ?? 0,
                'total_orders' => array_sum([
                    $statusBreakdown['unpaid'],
                    $statusBreakdown['paid'],
                    $statusBreakdown['failed'],
                ]),
                'status_breakdown' => $statusBreakdown,
                'total_reviews' => $this->productReviewRepository->getReviewCountForStore($storeId),
                'average_rating' => $this->productReviewRepository->getAverageRatingForStore($storeId),
                'total_products' => $this->productRepository->getProductCountForStore($storeId),
                'top_products' => $this->productRepository->getTopProducts($storeId, 5),
                'chart' => $this->transactionAnalyticsRepository->getChartData($this->resolveDays($request), 'store'),
                'trend' => $this->transactionAnalyticsRepository->getWeekOverWeekTrend(),
            ];

            return ResponseHelper::jsonResponse(true, 'success', new SellerDashboardResource($data), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function buyerSummary(Request $request)
    {
        if (! auth()->user()->hasRole('buyer')) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $data = [
                'total_expense' => $this->transactionAnalyticsRepository->getTotalRevenue('buyer'),
                'status_breakdown' => $this->transactionAnalyticsRepository->getStatusBreakdown('buyer'),
                'chart' => $this->transactionAnalyticsRepository->getChartData($this->resolveDays($request), 'buyer'),
            ];

            return ResponseHelper::jsonResponse(true, 'success', new BuyerDashboardResource($data), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function adminSummary()
    {
        if (! auth()->user()->hasRole('admin')) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $data = [
                'total_revenue' => $this->transactionAnalyticsRepository->getTotalRevenue(),
                'total_admin_fee' => $this->transactionAnalyticsRepository->getTotalAdminFee(),
                // Catatan: sesuai perilaku dashboard lama — "Total Seller" = semua toko,
                // "Total Toko" = toko terverifikasi saja.
                'total_sellers' => $this->storeRepository->getCount(),
                'total_buyers' => $this->userRepository->getCountByRole('buyer'),
                'total_products' => $this->productRepository->getTotalCount(),
                'total_transactions' => $this->transactionAnalyticsRepository->getTotalCount(),
                'total_stores' => $this->storeRepository->getCount(true),
                'latest_stores' => $this->storeRepository->getAll(null, true, 3, false, true),
                'latest_transactions' => $this->transactionRepository->getAll(null, 3, true),
            ];

            return ResponseHelper::jsonResponse(true, 'success', new AdminDashboardResource($data), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
