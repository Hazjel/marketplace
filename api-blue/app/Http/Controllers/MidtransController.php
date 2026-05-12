<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Repositories\StoreBalanceRepository;
use Illuminate\Support\Facades\Log;

use App\Interfaces\TransactionRepositoryInterface;

class MidtransController extends Controller
{
    protected $transactionRepository;

    public function __construct(TransactionRepositoryInterface $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    public function callback(Request $request)
    {
        // load server key from config (matches config/midtrans.php)
        $serverKey = config('midtrans.serverKey');

        // compute signature using Midtrans formula: order_id + status_code + gross_amount + server_key
        $hashedKey = hash('sha512', ($request->order_id ?? '') . ($request->status_code ?? '') . ($request->gross_amount ?? '') . ($serverKey ?? ''));

        // log incoming callback for debugging
        Log::info('Midtrans callback received', [
            'order_id' => $request->order_id ?? null,
            'status_code' => $request->status_code ?? null,
            'gross_amount' => $request->gross_amount ?? null,
            'signature_key' => $request->signature_key ?? null,
            'computed_signature' => $hashedKey,
        ]);

        if ($hashedKey !== ($request->signature_key ?? '')) {
            Log::warning('Midtrans signature mismatch', [
                'computed' => $hashedKey,
                'received' => $request->signature_key ?? null,
            ]);

            return response()->json(['message' => 'Invalid signature key'], 403);
        }

        $transactionStatus = $request->transaction_status;
        $transactionCode = $request->order_id;

        // Lock the transaction row to prevent concurrent duplicate webhook processing
        $transaction = Transaction::where('code', $transactionCode)->lockForUpdate()->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        switch ($transactionStatus) {
            case 'capture':
                if ($request->payment_type == 'credit_card') {
                    if ($request->fraud_status == 'challenge') {
                        $transaction->update(['payment_status' => 'unpaid']);
                    } else {
                        // Guard: skip if already paid (prevents double-credit on duplicate webhook)
                        if ($transaction->payment_status === 'paid') {
                            Log::info('Duplicate webhook ignored for: ' . $transactionCode);
                            break;
                        }
                        $transaction->update(['payment_status' => 'paid']);
                        $this->creditPendingBalance($transaction);
                    }
                }
                break;

            case 'settlement':
                // Guard: skip if already paid (prevents double-credit on duplicate webhook)
                if ($transaction->payment_status === 'paid') {
                    Log::info('Duplicate settlement webhook ignored for: ' . $transactionCode);
                    break;
                }
                $transaction->update(['payment_status' => 'paid']);
                $this->creditPendingBalance($transaction);
                break;

            case 'pending':
                $transaction->update(['payment_status' => 'unpaid']);
                break;

            case 'deny':
            case 'expire':
            case 'cancel':
                $transaction->update(['payment_status' => 'failed']);
                $this->transactionRepository->restoreStock($transaction);
                break;

            default:
                $transaction->update(['payment_status' => 'failed']);
                $this->transactionRepository->restoreStock($transaction);
                break;
        }

        // always return 200 after processing so Midtrans considers callback successful
        return response()->json(['message' => 'Payment Status updated successfully'], 200);
    }

    /**
     * Credit ke pending_balance (ESCROW) — dana ditahan sampai buyer konfirmasi terima.
     * Saldo baru pindah ke available balance setelah pesanan selesai (completed).
     */
    private function creditPendingBalance(Transaction $transaction): void
    {
        $store = Store::find($transaction->store_id);

        $netSales = $transaction->grand_total - $transaction->shipping_cost;
        $adminFee = $netSales * 0.10; // 10% platform fee
        $sellerAmount = $netSales - $adminFee;

        $transaction->admin_fee = $adminFee;
        $transaction->save();

        $transaction->load('transactionDetails.product');

        try {
            $storeBalanceRepository = new StoreBalanceRepository;

            if ($store && $store->storeBalance) {
                // Credit ke PENDING balance (bukan available balance)
                // Dana ditahan sampai buyer konfirmasi terima barang
                $storeBalanceRepository->creditPending($store->storeBalance->id, $sellerAmount);

                $storeBalance = $store->storeBalance;
                $storeBalance->storeBalanceHistories()->create([
                    'type' => 'pending_income',
                    'reference_id' => $transaction->id,
                    'reference_type' => Transaction::class,
                    'amount' => $sellerAmount,
                    'remarks' => 'Pembayaran diterima (ditahan) dari transaksi ' . $transaction->code . ' — akan dirilis setelah pesanan selesai',
                ]);

                Log::info('Pending balance credited for store: ' . $store->id, [
                    'net_sales' => $netSales,
                    'admin_fee' => $adminFee,
                    'seller_amount' => $sellerAmount,
                    'transaction_code' => $transaction->code,
                ]);
            } else {
                Log::error('Store Balance not found for store: ' . ($store->id ?? 'unknown'));
            }
        } catch (\Throwable $e) {
            Log::error('Error crediting pending balance: ' . $e->getMessage());
        }
    }
}
