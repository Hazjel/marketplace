<?php

namespace App\Repositories;

use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $mode = request('mode');

        $query = Transaction::with([
            'buyer.user',
            'store',
            'transactionDetails.product.store',
            'transactionDetails.product.productCategory',
            'transactionDetails.product.productImages',
        ])
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search);
                }
            });

        // User bisa dual-role (buyer + store) sejak dukung mode ganda ala
        // Shopee — $mode dari FE (?mode=store|buyer) menentukan KONTEKS,
        // scopeToMode tidak asumsikan role eksklusif seperti sebelumnya.
        if (! (auth()->check() && auth()->user()->hasRole('admin'))) {
            $this->scopeToMode($query, $mode);
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

    /**
     * Scope query ke store_id/buyer_id milik user login, berdasar $mode eksplisit.
     * Wajib dipakai (bukan cek hasRole berurutan) karena user bisa dual-role
     * (buyer + store sekaligus, sejak dukung mode ganda ala Shopee) — hasRole
     * saja tak cukup untuk tahu KONTEKS mana yang diminta caller.
     * Return false kalau user tidak punya akses ke mode yang diminta.
     */
    private function scopeToMode($query, ?string $mode): bool
    {
        if (! auth()->check()) {
            return false;
        }
        $user = auth()->user();

        if ($mode === 'store') {
            if (! $user->hasRole('store') || ! $user->store) {
                return false;
            }
            $query->where('store_id', $user->store->id);

            return true;
        }

        if ($mode === 'buyer') {
            if (! $user->hasRole('buyer') || ! $user->buyer) {
                return false;
            }
            $query->where('buyer_id', $user->buyer->id);

            return true;
        }

        // Tanpa mode eksplisit: admin lihat semua, non-admin default ke
        // scope store (prioritas) lalu buyer — pola lama dipertahankan
        // untuk pemanggil yang belum di-migrasi ke $mode eksplisit.
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->hasRole('store') && $user->store) {
            $query->where('store_id', $user->store->id);

            return true;
        }
        if ($user->hasRole('buyer') && $user->buyer) {
            $query->where('buyer_id', $user->buyer->id);

            return true;
        }

        return false;
    }

    public function getTotalRevenue(?string $mode = null)
    {
        $query = Transaction::where('payment_status', 'paid');

        if (! $this->scopeToMode($query, $mode)) {
            return 0;
        }

        return $query->sum('grand_total');
    }

    public function getTotalCount(): int
    {
        return Transaction::count();
    }

    public function getTotalAdminFee(?string $mode = null)
    {
        $query = Transaction::where('payment_status', 'paid');

        if (! $this->scopeToMode($query, $mode)) {
            return 0;
        }

        return $query->sum('admin_fee');
    }

    /**
     * Time-series revenue (store) atau pengeluaran (buyer) N hari terakhir.
     * $days dibatasi allow-list oleh controller (7/30/90).
     */
    public function getChartData(int $days = 7, ?string $mode = null)
    {
        $query = Transaction::query()->where('payment_status', 'paid');

        if (! $this->scopeToMode($query, $mode)) {
            return [];
        }

        $endDate = now('Asia/Jakarta')->endOfDay();
        $startDate = now('Asia/Jakarta')->subDays($days - 1)->startOfDay();
        $period = CarbonPeriod::create($startDate, $endDate);

        $transactions = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(grand_total) as total_revenue'),
                DB::raw('COUNT(*) as total_transaction')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        // Fill missing dates with 0
        $data = [];
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $record = $transactions->firstWhere('date', $dateString);

            $data[] = [
                'date' => $dateString,
                'total_revenue' => $record ? (int) $record->total_revenue : 0,
                'total_transaction' => $record ? (int) $record->total_transaction : 0,
            ];
        }

        return $data;
    }

    /**
     * Hitung transaksi per status (payment_status + delivery_status) untuk
     * user login, role-scoped. Single query pakai conditional SUM.
     */
    public function getStatusBreakdown(?string $mode = null)
    {
        $query = Transaction::query();

        if (! $this->scopeToMode($query, $mode)) {
            return [
                'unpaid' => 0, 'paid' => 0, 'failed' => 0,
                'pending' => 0, 'shipping' => 0, 'delivering' => 0,
                'delivered' => 0, 'completed' => 0, 'cancelled' => 0,
            ];
        }

        $row = $query->selectRaw("
            SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN delivery_status = 'shipping' THEN 1 ELSE 0 END) as shipping,
            SUM(CASE WHEN delivery_status = 'delivering' THEN 1 ELSE 0 END) as delivering,
            SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN delivery_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN delivery_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        ")->first();

        return [
            'unpaid' => (int) $row->unpaid,
            'paid' => (int) $row->paid,
            'failed' => (int) $row->failed,
            'pending' => (int) $row->pending,
            'shipping' => (int) $row->shipping,
            'delivering' => (int) $row->delivering,
            'delivered' => (int) $row->delivered,
            'completed' => (int) $row->completed,
            'cancelled' => (int) $row->cancelled,
        ];
    }

    /**
     * Bandingkan revenue & jumlah order 7 hari terakhir vs 7 hari sebelumnya
     * (store-only — dipakai untuk trend badge di dashboard seller).
     * Return null kalau bukan store (tidak ada perbandingan bermakna).
     */
    public function getWeekOverWeekTrend(): ?array
    {
        if (! auth()->check() || ! auth()->user()->hasRole('store')) {
            return null;
        }

        $storeId = auth()->user()->store?->id;
        if (! $storeId) {
            return null;
        }

        $now = now('Asia/Jakarta');
        $thisWeekStart = $now->copy()->subDays(6)->startOfDay();
        $lastWeekStart = $now->copy()->subDays(13)->startOfDay();
        $lastWeekEnd = $now->copy()->subDays(7)->endOfDay();

        $base = Transaction::where('store_id', $storeId)->where('payment_status', 'paid');

        $thisWeek = (clone $base)->whereBetween('created_at', [$thisWeekStart, $now])
            ->selectRaw('COALESCE(SUM(grand_total), 0) as revenue, COUNT(*) as orders')
            ->first();

        $lastWeek = (clone $base)->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->selectRaw('COALESCE(SUM(grand_total), 0) as revenue, COUNT(*) as orders')
            ->first();

        return [
            'revenue' => $this->percentChange((float) $thisWeek->revenue, (float) $lastWeek->revenue),
            'orders' => $this->percentChange((int) $thisWeek->orders, (int) $lastWeek->orders),
        ];
    }

    /**
     * Persentase perubahan; null kalau baseline 0 (tidak ada dasar perbandingan
     * yang bermakna — FE harus sembunyikan badge trend, bukan tampilkan Infinity).
     */
    private function percentChange(float $current, float $previous): ?array
    {
        if ($previous <= 0) {
            return null;
        }

        $percent = (($current - $previous) / $previous) * 100;

        return [
            'value' => round(abs($percent), 1),
            'direction' => $percent >= 0 ? 'up' : 'down',
        ];
    }

    public function getById(string $id)
    {
        $query = Transaction::where('id', $id)->with([
            'transactionDetails.product.productImages',
            'productReviews.user',
            'productReviews.attachments',
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

            $transaction->code = 'BLK'.now()->format('dmYHis').mt_rand(10, 99);
            $transaction->buyer_id = $data['buyer_id'];
            $transaction->store_id = $data['store_id'];
            $transaction->address_id = $data['address_id'];
            $transaction->address = $data['address'];
            $transaction->city = $data['city'];
            $transaction->postal_code = $data['postal_code'];
            $transaction->dest_latitude = $data['dest_latitude'] ?? null;
            $transaction->dest_longitude = $data['dest_longitude'] ?? null;
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
                Log::debug('REPO: Deduction loop for Product ID: '.$productData['product_id']);

                $product = Product::where('id', $productData['product_id'])->lockForUpdate()->first();

                if (! $product) {
                    Log::error('REPO: Product NOT FOUND ID: '.$productData['product_id']);
                    throw new Exception('Product not found: '.$productData['product_id']);
                }

                Log::debug("REPO: Found Prod {$product->id} | Stock: {$product->stock} | Qty: {$productData['qty']}");

                if ($product->stock < $productData['qty']) {
                    Log::error("REPO ERROR: Insufficient stock for {$product->id}. Has {$product->stock}, need {$productData['qty']}");
                    throw new Exception('Insufficient stock for product: '.$product->name);
                }

                // Deduct Stock
                $oldStock = $product->stock;
                $product->stock -= $productData['qty'];
                $product->save();

                Log::debug("REPO: Updated Stock {$oldStock} -> {$product->stock}");

                $detail = $transactionDetailRepository->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $productData['product_id'],
                    'qty' => $productData['qty'],
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
                'grand_total' => $grandTotal,
            ]);

            DB::commit();

            Log::info('=== BEFORE MIDTRANS ===');

            // Load Buyer & User for Midtrans
            $transaction->load('buyer.user');

            // Set your Merchant Server Key
            Config::$serverKey = config('midtrans.serverKey');
            Config::$isProduction = config('midtrans.isProduction');
            Config::$isSanitized = config('midtrans.isSanitized');
            Config::$is3ds = config('midtrans.is3ds');

            $params = [
                'transaction_details' => [
                    'order_id' => $transaction->code,
                    'gross_amount' => (int) $transaction->grand_total,
                ],
                'customer_details' => [
                    'first_name' => $transaction->buyer->user?->name ?? 'Customer',
                    'email' => $transaction->buyer->user?->email ?? 'no-email@example.com',
                ],
                'callbacks' => [
                    'finish' => env('FRONTEND_URL', 'http://localhost:5173').'/admin/transaction/'.$transaction->id,
                ],
                'expiry' => [
                    'start_time' => date('Y-m-d H:i:s O'),
                    'unit' => 'minute',
                    'duration' => 15,
                ],
            ];

            Log::info('Midtrans params:', ['params' => $params]);

            // Transaksi sudah ter-commit; kegagalan Midtrans tidak boleh
            // membuat request 500 padahal order & stok sudah tercatat.
            // FE sudah menangani snap_token null dengan pesan yang jelas.
            try {
                $snapToken = Snap::getSnapToken($params);

                Log::info('Snap token generated:', ['token' => $snapToken]);

                $transaction->snap_token = $snapToken;
                $transaction->save();
            } catch (\Throwable $e) {
                Log::error('Midtrans snap token failed: '.$e->getMessage(), [
                    'transaction' => $transaction->code,
                ]);
            }

            Log::info('=== TRANSACTION COMPLETED SUCCESSFULLY ===');

            return $transaction->fresh(['buyer', 'store', 'transactionDetails.product']);

        } catch (\Throwable $e) {
            DB::rollBack();
            $errorMsg = 'REPO FATAL ERROR: '.$e->getMessage()."\n".$e->getTraceAsString();
            Log::error($errorMsg);
            // file_put_contents(storage_path('logs/debug.txt'), $errorMsg, FILE_APPEND); // Reverted original or comment out
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
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function restoreStock(Transaction $transaction)
    {
        try {
            Log::info('Start restoring stock for transaction: '.$transaction->id);
            $transaction->load('transactionDetails');

            foreach ($transaction->transactionDetails as $detail) {
                // Pessimistic lock prevents double-restore from concurrent cancel + webhook
                $product = Product::where('id', $detail->product_id)->lockForUpdate()->first();
                if ($product) {
                    $product->stock += $detail->qty;
                    $product->save();
                    Log::info("Product {$product->id} RESTORED: Stock -> {$product->stock}");
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error restoring stock: '.$e->getMessage());
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

            if (isset($data['delivery_proof']) && $data['delivery_proof'] instanceof UploadedFile) {
                $transaction->delivery_proof = $data['delivery_proof']->store('assets/transaction', 'public');
            }

            // Restore stock if being cancelled/failed AND it wasn't already cancelled/failed
            if (isset($data['delivery_status']) &&
                in_array($data['delivery_status'], ['cancelled', 'failed']) &&
                ! in_array($transaction->delivery_status, ['cancelled', 'failed'])) {

                $this->restoreStock($transaction);

                // Refund escrow: kembalikan pending_balance jika payment sudah paid
                if ($transaction->payment_status === 'paid') {
                    $this->refundEscrow($transaction);
                }
            }

            if (isset($data['delivery_status'])) {
                $transaction->delivery_status = $data['delivery_status'];

                // Also sync payment status for consistency if cancelled
                if ($data['delivery_status'] === 'cancelled' && $transaction->payment_status !== 'failed') {
                    $transaction->payment_status = 'failed';
                }
            }

            $transaction->save();

            DB::commit();

            return $transaction->fresh([
                'buyer.user',
                'store.user',
                'transactionDetails.product',
            ]);
        } catch (Exception $e) {
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

        } catch (Exception $e) {
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
                'weight' => $weight,
            ]);

            if (! isset($data['store_id'])) {
                throw new Exception('store_id is missing from data');
            }

            $store = Store::find($data['store_id']);

            if (! $store) {
                throw new Exception('Store not found with id: '.$data['store_id']);
            }

            if (! $store->address_id) {
                throw new Exception('Store address_id is null for store: '.$store->id);
            }

            $origin = $store->address_id;

            if (! isset($data['address_id'])) {
                throw new Exception('address_id is missing from data');
            }

            $destination = $data['address_id'];

            // ✅ Convert weight ke gram (minimum 1 gram)
            $weightInGrams = max(1, round($weight * 1000));

            Log::info('Calling RajaOngkir API with:', [
                'origin' => $origin,
                'destination' => $destination,
                'subtotal' => round($subtotal),
                'weight' => $weightInGrams,  // dalam gram
            ]);

            // Komerce RajaOngkir API
            $response = Http::withHeaders([
                'x-api-key' => env('KEY_RAJA_ONGKIR'),
            ])->get('https://api-sandbox.collaborator.komerce.id/tariff/api/v1/calculate', [
                'shipper_destination_id' => $origin,
                'receiver_destination_id' => $destination,
                'item_value' => round($subtotal),
                'weight' => $weightInGrams,  // kirim dalam gram
            ]);

            $result = $response->json();

            Log::info('RajaOngkir API Response:', ['result' => $result]);

            // Validasi response structure
            if (! isset($result['data']) || $result['data'] === null) {
                Log::error('API returned error:', ['response' => $result]);
                throw new Exception('RajaOngkir API error: '.($result['meta']['message'] ?? 'Unknown error'));
            }

            if (! isset($result['data']['calculate_reguler'])) {
                throw new Exception('Invalid API response - missing calculate_reguler key');
            }

            $shippingCost = 0;

            if (! isset($data['shipping'])) {
                throw new Exception('shipping is missing from data');
            }

            if (! isset($data['shipping_type'])) {
                throw new Exception('shipping_type is missing from data');
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
                    'shipping_type' => $data['shipping_type'],
                ]);
            }

            $tax = round($subtotal * 0.11, 2);
            $grandTotal = round($subtotal + $tax + $shippingCost, 2);

            Log::info('Calculation completed:', [
                'shipping_cost' => $shippingCost,
                'tax' => $tax,
                'grand_total' => $grandTotal,
            ]);

            return [
                'shipping_cost' => $shippingCost,
                'tax' => $tax,
                'grand_total' => $grandTotal,
            ];

        } catch (Exception $e) {
            Log::error('Shipping calculation error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Refund escrow: kembalikan pending_balance saat transaksi yang sudah paid dibatalkan.
     * Dana dikembalikan ke buyer (di luar sistem), pending_balance seller dikurangi.
     */
    private function refundEscrow(Transaction $transaction): void
    {
        try {
            $store = Store::find($transaction->store_id);

            if (! $store || ! $store->storeBalance) {
                Log::error('refundEscrow: Store or StoreBalance not found', [
                    'store_id' => $transaction->store_id,
                ]);

                return;
            }

            $netSales = $transaction->grand_total - $transaction->shipping_cost;
            $adminFee = $netSales * config('marketplace.admin_fee_percentage');
            $sellerAmount = $netSales - $adminFee;

            $storeBalanceRepository = new StoreBalanceRepository;
            $storeBalanceRepository->refundPending($store->storeBalance->id, $sellerAmount);

            // Catat history refund
            $store->storeBalance->storeBalanceHistories()->create([
                'type' => 'refunded',
                'reference_id' => $transaction->id,
                'reference_type' => Transaction::class,
                'amount' => -$sellerAmount,
                'remarks' => 'Escrow dibatalkan (refund) — pesanan '.$transaction->code.' dibatalkan',
            ]);

            Log::info('Escrow refunded for transaction: '.$transaction->code, [
                'store_id' => $store->id,
                'refunded_amount' => $sellerAmount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error refunding escrow: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
            ]);
        }
    }
}
