<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShippingOptionsRequest;
use App\Repositories\TransactionRepository;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    public function options(ShippingOptionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Calculate subtotal and total weight from products
        $subtotal = 0;
        $weight = 0;

        foreach ($data['products'] as $p) {
            $product = Product::find($p['product_id']);
            if (!$product) continue;
            $qty = (float) $p['qty'];
            $subtotal += ($product->price ?? 0) * $qty;
            $weight += ($product->weight ?? 0) * $qty;
        }

        Log::info('Shipping options requested:', ['store_id' => $data['store_id'], 'address_id' => $data['address_id'], 'subtotal' => $subtotal, 'weight' => $weight]);

        $repo = new TransactionRepository();

        $options = $repo->getShippingOptions($data, $subtotal, $weight);

        return response()->json([
            'success' => true,
            'data' => [
                'options' => $options,
                'subtotal' => $subtotal,
                'weight' => $weight
            ]
        ]);
    }
}
