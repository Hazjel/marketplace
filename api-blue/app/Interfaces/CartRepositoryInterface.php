<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

interface CartRepositoryInterface
{
    public function getByUserId(string $userId): Collection;

    public function addOrUpdate(string $userId, array $data): \App\Models\Cart;

    public function updateQuantity(string $userId, string $productId, ?string $variantId, int $quantity): \App\Models\Cart;

    public function remove(string $userId, string $productId, ?string $variantId): void;

    public function clear(string $userId): void;

    public function syncFromLocal(string $userId, array $items): Collection;

    public function validateStock(array $items): array;
}
