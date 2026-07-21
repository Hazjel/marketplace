<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\BuyerDashboardResource;
use App\Http\Resources\SellerDashboardResource;
use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\ProductReviewRepositoryInterface;
use App\Interfaces\StoreBalanceRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private ProductRepositoryInterface $productRepository,
        private ProductReviewRepositoryInterface $productReviewRepository,
        private StoreBalanceRepositoryInterface $storeBalanceRepository,
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
            $statusBreakdown = $this->transactionRepository->getStatusBreakdown();

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
                'chart' => $this->transactionRepository->getChartData($this->resolveDays($request)),
                'trend' => $this->transactionRepository->getWeekOverWeekTrend(),
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
                'total_expense' => $this->transactionRepository->getTotalRevenue(),
                'status_breakdown' => $this->transactionRepository->getStatusBreakdown(),
                'chart' => $this->transactionRepository->getChartData($this->resolveDays($request)),
            ];

            return ResponseHelper::jsonResponse(true, 'success', new BuyerDashboardResource($data), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
