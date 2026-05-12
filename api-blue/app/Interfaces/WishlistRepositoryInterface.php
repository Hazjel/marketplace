<?php

namespace App\Interfaces;

interface WishlistRepositoryInterface
{
    public function getByUserId($userId);
    public function toggle($userId, $productId);
}
