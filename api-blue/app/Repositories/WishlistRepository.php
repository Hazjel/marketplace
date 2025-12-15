<?php

namespace App\Repositories;

use App\Interfaces\WishlistRepositoryInterface;
use App\Models\Wishlist;

class WishlistRepository implements WishlistRepositoryInterface
{
    public function getByUserId($userId)
    {
        return Wishlist::with(['product.productImages', 'product.store'])->where('user_id', $userId)->get();
    }

    public function toggle($userId, $productId)
    {
        $wishlist = Wishlist::where('user_id', $userId)->where('product_id', $productId)->first();

        if ($wishlist) {
            $wishlist->delete();
            return 'removed';
        } else {
            Wishlist::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);
            return 'added';
        }
    }
}
