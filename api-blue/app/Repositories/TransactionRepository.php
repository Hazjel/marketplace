<?php

namespace App\Repositories;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Repositories\TransactionDetailRepository;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $query = Transaction::with([
            'buyer.user', 
            'store', 
            'transactionDetails.product.store', 
            'transactionDetails.product.productCategory',
            'transactionDetails.product.productImages'
        ])
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search);
                }
            });

        if (auth()->check() && auth()->user()->hasRole('store')) {
            $query->where('store_id', auth()->user()->store?->id ?? null);
        }

        if (auth()->check() && auth()->user()->hasRole('buyer')) {
            $query->where('buyer_id', auth()->user()->buyer?->id ?? null);
        }

        $query->orderBy('created_at', 'desc');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(?string $search, ?int $rowPerPage)
    {
        $query = $this->getAll($search, null, false);

        return $query->paginate($rowPerPage);
    }

    public function getTotalRevenue()
    {
        $query = Transaction::where('payment_status', 'paid');
        
        if (auth()->check() && auth()->user()->hasRole('store')) {
            $query->where('store_id', auth()->user()->store?->id ?? null);
        }

        if (auth()->check() && auth()->user()->hasRole('buyer')) {
            $query->where('buyer_id', auth()->user()->buyer?->id ?? null);
        }

        return $query->sum('grand_total');
    }

    public function getTotalAdminFee()
    {
        $query = Transaction::where('payment_status', 'paid');
        
        if (auth()->check() && auth()->user()->hasRole('store')) {
            $query->where('store_id', auth()->user()->store?->id ?? null);
        }

        if (auth()->check() && auth()->user()->hasRole('buyer')) {
            $query->where('buyer_id', auth()->user()->buyer?->id ?? null);
        }

        return $query->sum('admin_fee');
    }



    public function getById(string $id)
    {
        $query = Transaction::where('id', $id)->with([
            'transactionDetails.product.productImages',
            'productReviews.user',
            'productReviews.attachments'
        ]);

        return $query->first();
    }

    public function getByCode(string $code)
    {
        $query = Transaction::where('code', $code);

        return $query->first();
    }

    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            Log::info('=== START CREATE TRANSACTION ===');
            Log::info('Input data:', ['data' => $data]);

            $transaction = new Transaction;

            $transaction->code = 'BLUE' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $transaction->buyer_id = $data['buyer_id'];
            $transaction->store_id = $data['store_id'];
            $transaction->address_id = $data['address_id'];
            $transaction->address = $data['address'];
            $transaction->city = $data['city'];
            $transaction->postal_code = $data['postal_code'];
            $transaction->shipping = $data['shipping'];
            $transaction->shipping_type = $data['shipping_type'];

            // ✅ Gunakan shipping_cost dari frontend
            $transaction->shipping_cost = $data['shipping_cost'] ?? 0;
            $transaction->tax = 0;
            $transaction->grand_total = 0;
            $transaction->save();

            Log::info('Transaction created:', ['transaction_id' => $transaction->id]);

            $transactionDetailRepository = new TransactionDetailRepository;
            $transactionDetails = [];

            foreach ($data['products'] as $productData) {
                // Find Product with Lock for Atomic Update
                Log::error("REPO: Deduction loop for Product ID: " . $productData['product_id']);
                
                $product = Product::where('id', $productData['product_id'])->lockForUpdate()->first();

                if (!$product) {
                    Log::error("REPO: Product NOT FOUND ID: " . $productData['product_id']);
                    throw new Exception("Product not found: " . $productData['product_id']);
                }

                Log::error("REPO: Found Prod {$product->id} | Stock: {$product->stock} | Qty: {$productData['qty']}");

                if ($product->stock < $productData['qty']) {
                    Log::error("REPO ERROR: Insufficient stock for {$product->id}. Has {$product->stock}, need {$productData['qty']}");
                    throw new Exception("Insufficient stock for product: " . $product->name);
                }

                // Deduct Stock
                $oldStock = $product->stock;
                $product->stock -= $productData['qty'];
                $product->save();
                
                Log::error("REPO SUCCESS: Updated Stock {$oldStock} -> {$product->stock}");

                $detail = $transactionDetailRepository->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $productData['product_id'],
                    'qty' => $productData['qty']
                ]);

                $detail->load('product');
                $transactionDetails[] = $detail;
            }

            Log::info('Transaction details created:', ['count' => count($transactionDetails)]);

            // ✅ Hitung subtotal dari produk saja
            $subtotal = array_reduce($transactionDetails, function ($carry, $item) {
                return $carry + $item->subtotal;
            }, 0);

            Log::info('Subtotal calculated:', ['subtotal' => $subtotal]);

            // ✅ Hitung tax dan grand total (TIDAK pakai API lagi)
            $tax = round($subtotal * 0.11);
            $grandTotal = round($subtotal + $tax + $transaction->shipping_cost);

            $transaction->tax = $tax;
            $transaction->grand_total = $grandTotal;
            $transaction->save();

            Log::info('Transaction updated with costs:', [
                'subtotal' => $subtotal,
                'shipping_cost' => $transaction->shipping_cost,
                'tax' => $tax,
                'grand_total' => $grandTotal
            ]);

            DB::commit();

            Log::info('=== BEFORE MIDTRANS ===');

            // Load Buyer & User for Midtrans
            $transaction->load('buyer.user');

            // Set your Merchant Server Key
            \Midtrans\Config::$serverKey = config('midtrans.serverKey');
            \Midtrans\Config::$isProduction = config('midtrans.isProduction');
            \Midtrans\Config::$isSanitized = config('midtrans.isSanitized');
            \Midtrans\Config::$is3ds = config('midtrans.is3ds');

            $params = array(
                'transaction_details' => array(
                    'order_id' => $transaction->code,
                    'gross_amount' => (int) $transaction->grand_total
                ),
                'customer_details' => array(
                    'first_name' => $transaction->buyer->user?->name ?? 'Customer',
                    'email' => $transaction->buyer->user?->email ?? 'no-email@example.com',
                ),
            );

            Log::info('Midtrans params:', ['params' => $params]);

            $snapToken = \Midtrans\Snap::getSnapToken($params);

            Log::info('Snap token generated:', ['token' => $snapToken]);

            $transaction->snap_token = $snapToken;
            $transaction->save();

            Log::info('=== TRANSACTION COMPLETED SUCCESSFULLY ===');

            return $transaction->fresh(['buyer', 'store', 'transactionDetails.product']);

        } catch (\Throwable $e) {
            DB::rollBack();
            $errorMsg = "REPO FATAL ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
            Log::error($errorMsg);
            file_put_contents(storage_path('logs/debug.txt'), $errorMsg, FILE_APPEND);
            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $transaction = Transaction::find($id);

            // Restore stock if transaction is being deleted and was holding stock (pending/unpaid)
            if (in_array($transaction->payment_status, ['pending', 'unpaid'])) {
                $this->restoreStock($transaction);
            }

            $transaction->delete();

            DB::commit();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function restoreStock(Transaction $transaction)
    {
        try {
            Log::info('Start restoring stock for transaction: ' . $transaction->id);
            $transaction->load('transactionDetails.product');
            
            foreach ($transaction->transactionDetails as $detail) {
                $product = $detail->product;
                if ($product) {
                    $product->stock += $detail->qty;
                    $product->save();
                    Log::info("Product {$product->id} RESTORED: Stock -> {$product->stock}");
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error restoring stock: ' . $e->getMessage());
        }
    }

    public function updateStatus(string $id, array $data)
    {
        DB::beginTransaction();

        try {
            $transaction = Transaction::find($id);


            if (isset($data['tracking_number'])) {
                $transaction->tracking_number = $data['tracking_number'];
            }

            if (isset($data['delivery_proof'])) {
                $transaction->delivery_proof = $data['delivery_proof']->store('assets/transaction', 'public');
            }

            $transaction->delivery_status = $data['delivery_status'];
            $transaction->save();

            DB::commit();

            return $transaction->fresh([
                'buyer.user',
                'store.user',
                'transactionDetails.product'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    private function getTotalWeight(array $transactionDetails)
    {
        try {
            if (empty($transactionDetails)) {
                return 0;
            }

            $totalWeight = 0;

            foreach ($transactionDetails as $detail) {
                // Eager load product relationship jika belum
                $product = $detail->product ?? Product::find($detail->product_id);

                if ($product && $product->weight) {
                    $totalWeight += $product->weight * $detail->qty;
                }
            }

            Log::info('Total weight calculated:', ['weight' => $totalWeight]);

            return $totalWeight;

        } catch (\Exception $e) {
            Log::error('Error calculating weight:', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function calculateShippingAndTax(array $data, float $subtotal, float $weight): array
    {
        try {
            Log::info('calculateShippingAndTax called with:', [
                'data' => $data,
                'subtotal' => $subtotal,
                'weight' => $weight
            ]);

            if (!isset($data['store_id'])) {
                throw new \Exception('store_id is missing from data');
            }

            $store = Store::find($data['store_id']);

            if (!$store) {
                throw new \Exception('Store not found with id: ' . $data['store_id']);
            }

            if (!$store->address_id) {
                throw new \Exception('Store address_id is null for store: ' . $store->id);
            }

            $origin = $store->address_id;

            if (!isset($data['address_id'])) {
                throw new \Exception('address_id is missing from data');
            }

            $destination = $data['address_id'];

            // ✅ Convert weight ke gram (minimum 1 gram)
            $weightInGrams = max(1, round($weight * 1000));

            Log::info('Calling RajaOngkir API with:', [
                'origin' => $origin,
                'destination' => $destination,
                'subtotal' => round($subtotal),
                'weight' => $weightInGrams  // dalam gram
            ]);

            // Komerce RajaOngkir API
            $response = Http::withHeaders([
                'x-api-key' => env('KEY_RAJA_ONGKIR'),
            ])->get('https://api-sandbox.collaborator.komerce.id/tariff/api/v1/calculate', [
                        'shipper_destination_id' => $origin,
                        'receiver_destination_id' => $destination,
                        'item_value' => round($subtotal),
                        'weight' => $weightInGrams  // kirim dalam gram
                    ]);

            $result = $response->json();

            Log::info('RajaOngkir API Response:', ['result' => $result]);

            // Validasi response structure
            if (!isset($result['data']) || $result['data'] === null) {
                Log::error('API returned error:', ['response' => $result]);
                throw new \Exception('RajaOngkir API error: ' . ($result['meta']['message'] ?? 'Unknown error'));
            }

            if (!isset($result['data']['calculate_reguler'])) {
                throw new \Exception('Invalid API response - missing calculate_reguler key');
            }

            $shippingCost = 0;

            if (!isset($data['shipping'])) {
                throw new \Exception('shipping is missing from data');
            }

            if (!isset($data['shipping_type'])) {
                throw new \Exception('shipping_type is missing from data');
            }

            // Find matching courier and service
            foreach ($result['data']['calculate_reguler'] as $courier) {
                if (
                    strtolower($courier['shipping_name']) === strtolower($data['shipping']) &&
                    strtoupper($courier['service_name']) === strtoupper($data['shipping_type'])
                ) {
                    $shippingCost = $courier['shipping_cost_net'];
                    Log::info('Matching courier found:', ['shipping_cost' => $shippingCost]);
                    break;
                }
            }

            if ($shippingCost === 0) {
                Log::warning('No matching courier found for:', [
                    'shipping' => $data['shipping'],
                    'shipping_type' => $data['shipping_type']
                ]);
            }

            $tax = round($subtotal * 0.11, 2);
            $grandTotal = round($subtotal + $tax + $shippingCost, 2);

            Log::info('Calculation completed:', [
                'shipping_cost' => $shippingCost,
                'tax' => $tax,
                'grand_total' => $grandTotal
            ]);

            return [
                'shipping_cost' => $shippingCost,
                'tax' => $tax,
                'grand_total' => $grandTotal
            ];

        } catch (\Exception $e) {
            Log::error('Shipping calculation error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
}
