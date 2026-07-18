<?php

namespace App\Repositories;

use App\Interfaces\ProductViewRepositoryInterface;
use App\Models\ProductView;
use Illuminate\Support\Carbon;

class ProductViewRepository implements ProductViewRepositoryInterface
{
    /**
     * Dedup window -- satu user/session gak nyatet ulang view produk yang
     * sama dalam window ini, biar refresh berkali-kali gak nge-spam data.
     */
    private const DEDUP_MINUTES = 10;

    public function record($userId, $sessionId, $productId): bool
    {
        $query = ProductView::where('product_id', $productId)
            ->where('viewed_at', '>=', Carbon::now()->subMinutes(self::DEDUP_MINUTES));

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id')->where('session_id', $sessionId);
        }

        if ($query->exists()) {
            return false;
        }

        ProductView::create([
            'user_id' => $userId,
            'session_id' => $userId ? null : $sessionId,
            'product_id' => $productId,
            'viewed_at' => Carbon::now(),
        ]);

        return true;
    }
}
