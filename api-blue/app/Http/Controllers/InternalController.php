<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Buyer;
use App\Models\ProductReview;
use App\Models\ProductView;
use App\Models\TransactionDetail;
use App\Models\Wishlist;

/**
 * Endpoint khusus diakses recommendation-service (lewat middleware "internal"),
 * bukan lewat auth token user -- return data interaksi agregat lintas user
 * buat training model collaborative filtering (SVD).
 */
class InternalController extends Controller
{
    /**
     * Bobot per jenis interaksi -- transaksi selesai sinyal paling kuat
     * (user beneran beli & bayar), makin ke bawah sinyalnya makin lemah.
     */
    private const WEIGHT_PURCHASE = 5.0;

    private const WEIGHT_REVIEW_MULTIPLIER = 1.0; // dikali rating (1-5)

    private const WEIGHT_WISHLIST = 3.0;

    private const WEIGHT_VIEW = 1.0;

    public function interactions()
    {
        $interactions = [];

        $buyerUserMap = Buyer::whereNotNull('user_id')->pluck('user_id', 'id');

        TransactionDetail::whereHas('transaction', function ($q) {
            $q->where('payment_status', 'paid');
        })
            ->with('transaction:id,buyer_id')
            ->get(['id', 'transaction_id', 'product_id'])
            ->each(function ($detail) use (&$interactions, $buyerUserMap) {
                $userId = $buyerUserMap->get($detail->transaction->buyer_id);
                if (! $userId) {
                    return;
                }
                $interactions[] = [
                    'user_id' => $userId,
                    'product_id' => $detail->product_id,
                    'type' => 'purchase',
                    'weight' => self::WEIGHT_PURCHASE,
                ];
            });

        ProductReview::whereNotNull('user_id')
            ->get(['user_id', 'product_id', 'rating'])
            ->each(function ($review) use (&$interactions) {
                $interactions[] = [
                    'user_id' => $review->user_id,
                    'product_id' => $review->product_id,
                    'type' => 'review',
                    'weight' => (float) $review->rating * self::WEIGHT_REVIEW_MULTIPLIER,
                ];
            });

        Wishlist::get(['user_id', 'product_id'])
            ->each(function ($wishlist) use (&$interactions) {
                $interactions[] = [
                    'user_id' => $wishlist->user_id,
                    'product_id' => $wishlist->product_id,
                    'type' => 'wishlist',
                    'weight' => self::WEIGHT_WISHLIST,
                ];
            });

        ProductView::whereNotNull('user_id')
            ->get(['user_id', 'product_id'])
            ->each(function ($view) use (&$interactions) {
                $interactions[] = [
                    'user_id' => $view->user_id,
                    'product_id' => $view->product_id,
                    'type' => 'view',
                    'weight' => self::WEIGHT_VIEW,
                ];
            });

        return ResponseHelper::jsonResponse(true, 'Data interaksi berhasil diambil', $interactions, 200);
    }
}
