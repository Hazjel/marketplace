<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CartResource;
use App\Interfaces\CartRepositoryInterface;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private CartRepositoryInterface $cartRepository;

    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    public function index()
    {
        try {
            $carts = $this->cartRepository->getByUserId(auth()->id());

            // Group by store for frontend consumption
            $grouped = $carts->groupBy(fn ($cart) => $cart->product->store_id)
                ->map(function ($items, $storeId) {
                    $firstProduct = $items->first()->product;

                    return [
                        'store_id' => $storeId,
                        'store_name' => $firstProduct->store->name ?? '-',
                        'store_logo' => $firstProduct->store->logo ?? null,
                        'store_address_id' => $firstProduct->store->address_id ?? null,
                        'items' => CartResource::collection($items),
                    ];
                })
                ->values();

            return ResponseHelper::jsonResponse(true, 'Data Cart Berhasil Diambil', $grouped, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $cart = $this->cartRepository->addOrUpdate(auth()->id(), $request->only([
                'product_id', 'variant_id', 'quantity', 'note',
            ]));

            $cart->load(['product.store', 'product.productCategory']);

            return ResponseHelper::jsonResponse(true, 'Produk berhasil ditambahkan ke keranjang', new CartResource($cart), 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, string $productId)
    {
        $request->validate([
            'variant_id' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $cart = $this->cartRepository->updateQuantity(
                auth()->id(),
                $productId,
                $request->variant_id,
                $request->quantity
            );

            return ResponseHelper::jsonResponse(true, 'Jumlah berhasil diperbarui', new CartResource($cart), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(Request $request, string $productId)
    {
        try {
            $this->cartRepository->remove(
                auth()->id(),
                $productId,
                $request->query('variant_id')
            );

            return ResponseHelper::jsonResponse(true, 'Produk berhasil dihapus dari keranjang', null, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function clear()
    {
        try {
            $this->cartRepository->clear(auth()->id());

            return ResponseHelper::jsonResponse(true, 'Keranjang berhasil dikosongkan', null, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function sync(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:500',
        ]);

        try {
            $carts = $this->cartRepository->syncFromLocal(auth()->id(), $request->items);

            // Return grouped format like index
            $grouped = $carts->groupBy(fn ($cart) => $cart->product->store_id)
                ->map(function ($items, $storeId) {
                    $firstProduct = $items->first()->product;

                    return [
                        'store_id' => $storeId,
                        'store_name' => $firstProduct->store->name ?? '-',
                        'store_logo' => $firstProduct->store->logo ?? null,
                        'store_address_id' => $firstProduct->store->address_id ?? null,
                        'items' => CartResource::collection($items),
                    ];
                })
                ->values();

            return ResponseHelper::jsonResponse(true, 'Cart berhasil disinkronisasi', $grouped, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function validateStock(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.variant_id' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $results = $this->cartRepository->validateStock($request->items);

            $allValid = collect($results)->every(fn ($r) => $r['valid']);

            return ResponseHelper::jsonResponse(
                true,
                $allValid ? 'Semua stok tersedia' : 'Beberapa produk stoknya tidak mencukupi',
                [
                    'all_valid' => $allValid,
                    'items' => $results,
                ],
                200
            );
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
