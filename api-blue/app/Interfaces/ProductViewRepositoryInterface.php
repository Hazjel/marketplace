<?php

namespace App\Interfaces;

interface ProductViewRepositoryInterface
{
    public function record($userId, $sessionId, $productId): bool;
}
