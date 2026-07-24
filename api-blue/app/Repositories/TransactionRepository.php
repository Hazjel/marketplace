<?php

namespace App\Repositories;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Product;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private EscrowRepositoryInterface $escrowRepository,
        private PaymentGatewayInterface $paymentGateway
    ) {}

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
        if (! (Auth::check() && Auth::user()->hasRole('admin'))) {
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
        if (! Auth::check()) {
            return false;
        }
        $user = Auth::user();

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

            // Transaksi sudah ter-commit; kegagalan gateway tidak boleh
            // membuat request 500 padahal order & stok sudah tercatat.
            // FE sudah menangani snap_token null dengan pesan yang jelas.
            $snapToken = $this->paymentGateway->getSnapToken($transaction);
            if ($snapToken !== null) {
                $transaction->snap_token = $snapToken;
                $transaction->save();
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
                    $this->escrowRepository->refund($transaction);
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
}
