<?php

namespace App\Repositories;

use App\Interfaces\CartRepositoryInterface;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariantMongo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CartRepository implements CartRepositoryInterface
{
    /**
     * Scope helper: match variant_id (handles NULL correctly for MySQL).
     */
    private function scopeVariant(Builder $query, ?string $variantId): Builder
    {
        return $variantId === null
            ? $query->whereNull('variant_id')
            : $query->where('variant_id', $variantId);
    }

    public function getByUserId(string $userId): Collection
    {
        return Cart::with(['product.productImages', 'product.store', 'product.productCategory'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function addOrUpdate(string $userId, array $data): Cart
    {
        $variantId = $data['variant_id'] ?? null;

        $query = Cart::where('user_id', $userId)
            ->where('product_id', $data['product_id']);

        $cart = $this->scopeVariant($query, $variantId)->first();

        if ($cart) {
            $cart->quantity += $data['quantity'] ?? 1;
            $cart->save();

            return $cart;
        }

        return Cart::create([
            'user_id' => $userId,
            'product_id' => $data['product_id'],
            'variant_id' => $variantId,
            'quantity' => $data['quantity'] ?? 1,
            'note' => $data['note'] ?? null,
        ]);
    }

    public function updateQuantity(string $userId, string $productId, ?string $variantId, int $quantity): Cart
    {
        $query = Cart::where('user_id', $userId)
            ->where('product_id', $productId);

        $cart = $this->scopeVariant($query, $variantId)->firstOrFail();

        $cart->quantity = $quantity;
        $cart->save();

        return $cart;
    }

    public function remove(string $userId, string $productId, ?string $variantId): void
    {
        $query = Cart::where('user_id', $userId)
            ->where('product_id', $productId);

        $this->scopeVariant($query, $variantId)->delete();
    }

    public function clear(string $userId): void
    {
        Cart::where('user_id', $userId)->delete();
    }

    /**
     * Merge localStorage items into server cart.
     * Strategy: keep the HIGHER quantity between local and server for each item.
     */
    public function syncFromLocal(string $userId, array $items): Collection
    {
        DB::beginTransaction();

        try {
            foreach ($items as $item) {
                $variantId = $item['variant_id'] ?? null;

                $query = Cart::where('user_id', $userId)
                    ->where('product_id', $item['product_id']);

                $existing = $this->scopeVariant($query, $variantId)->first();

                $incomingQty = max(1, (int) ($item['quantity'] ?? 1));

                if ($existing) {
                    $existing->quantity = max($existing->quantity, $incomingQty);
                    $existing->save();
                } else {
                    Cart::create([
                        'user_id' => $userId,
                        'product_id' => $item['product_id'],
                        'variant_id' => $variantId,
                        'quantity' => $incomingQty,
                        'note' => $item['note'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return $this->getByUserId($userId);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Batch validate stock for multiple cart items.
     * Returns array of validation results per item.
     */
    public function validateStock(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Collect variant IDs for batch MongoDB lookup
        $variantIds = collect($items)
            ->pluck('variant_id')
            ->filter()
            ->unique()
            ->toArray();

        $variants = [];
        if (!empty($variantIds)) {
            try {
                $variants = ProductVariantMongo::whereIn('_id', $variantIds)
                    ->get()
                    ->keyBy(fn ($v) => (string) $v->_id);
            } catch (\Exception) {
                // MongoDB unavailable — gracefully skip variant stock check
                $variants = [];
            }
        }

        $results = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $requestedQty = (int) ($item['quantity'] ?? 1);

            if (!$product) {
                $results[] = [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'available' => 0,
                    'requested' => $requestedQty,
                    'valid' => false,
                    'reason' => 'product_not_found',
                ];
                continue;
            }

            // Determine available stock: variant stock takes precedence
            $availableStock = $product->stock;
            if (!empty($item['variant_id']) && isset($variants[$item['variant_id']])) {
                $availableStock = $variants[$item['variant_id']]->stock;
            }

            $results[] = [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'product_name' => $product->name,
                'available' => $availableStock,
                'requested' => $requestedQty,
                'valid' => $requestedQty <= $availableStock,
                'reason' => $requestedQty > $availableStock ? 'insufficient_stock' : null,
            ];
        }

        return $results;
    }
}
