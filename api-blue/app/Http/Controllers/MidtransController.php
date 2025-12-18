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
        $transaction = Transaction::where('code', $transactionCode)->first();
        
        file_put_contents(storage_path('logs/debug.txt'), "RX CALLBACK: Status=$transactionStatus Type={$request->payment_type}\n", FILE_APPEND);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        switch ($transactionStatus) {
            case 'capture':
                file_put_contents(storage_path('logs/debug.txt'), "STATUS: CAPTURE\n", FILE_APPEND);
                if ($request->payment_type == 'credit_card') {
                    if ($request->fraud_status == 'challenge') {
                        $transaction->update(['payment_status' => 'unpaid']);
                    } else {
                        $transaction->update(['payment_status' => 'paid']);

                        $store = Store::find($transaction->store_id);

                        $netSales = $transaction->grand_total - $transaction->shipping_cost;
                        $adminFee = $netSales * 0.10; // 10% fee
                        
                        $transaction->admin_fee = $adminFee;
                        $transaction->save();
                        
                        // Explicitly load relationships to ensure loop works
                        $transaction->load('transactionDetails.product');
                        
                        try {
                            $storeBalanceRepository = new StoreBalanceRepository;
                        
                            if ($store && $store->storeBalance) {
                                // 1. Credit Full Amount (Income)
                                $storeBalanceRepository->credit($store->storeBalance->id, $netSales);
                                
                                $storeBalance = $store->storeBalance;
                                $storeBalance->storeBalanceHistories()->create([
                                    'type' => 'income',
                                    'reference_id' => $transaction->id,
                                    'reference_type' => Transaction::class,
                                    'amount' => $netSales,
                                    'remarks' => 'Payment received from transaction ' . $transaction->code,
                                ]);

                                // 2. Debit Admin Fee (Expense)
                                $storeBalanceRepository->debit($store->storeBalance->id, $adminFee);
                                
                                $storeBalance->storeBalanceHistories()->create([
                                    'type' => 'expense',
                                    'reference_id' => $transaction->id,
                                    'reference_type' => Transaction::class,
                                    'amount' => -$adminFee,
                                    'remarks' => 'Admin Fee (10%) for transaction ' . $transaction->code,
                                ]);
                            } else {
                                Log::error('Store Balance not found for store: ' . ($store->id ?? 'unknown'));
                            }
                        } catch (\Throwable $e) {
                            Log::error('Error updating store balance: ' . $e->getMessage());
                        }
                    }
                }
                break;

            case 'settlement':
                file_put_contents(storage_path('logs/debug.txt'), "STATUS: SETTLEMENT\n", FILE_APPEND);
                $transaction->update(['payment_status' => 'paid']);

                $store = Store::find($transaction->store_id);

                $netSales = $transaction->grand_total - $transaction->shipping_cost;
                $adminFee = $netSales * 0.10; // 10% fee
                
                $transaction->admin_fee = $adminFee;
                $transaction->save();
                
                $transaction->load('transactionDetails.product');
                
                try {
                    $storeBalanceRepository = new StoreBalanceRepository;
                    
                    if ($store && $store->storeBalance) {
                        $storeBalanceRepository->credit($store->storeBalance->id, $netSales);
                        
                        $storeBalance = $store->storeBalance;
                        $storeBalance->storeBalanceHistories()->create([
                            'type' => 'income',
                            'reference_id' => $transaction->id,
                            'reference_type' => Transaction::class,
                            'amount' => $netSales,
                            'remarks' => 'Payment received from transaction ' . $transaction->code,
                        ]);

                        $storeBalanceRepository->debit($store->storeBalance->id, $adminFee);
                        
                        $storeBalance->storeBalanceHistories()->create([
                            'type' => 'expense',
                            'reference_id' => $transaction->id,
                            'reference_type' => Transaction::class,
                            'amount' => -$adminFee,
                            'remarks' => 'Admin Fee (10%) for transaction ' . $transaction->code,
                        ]);
                    } else {
                        Log::error('Store Balance not found for store: ' . ($store->id ?? 'unknown'));
                    }
                } catch (\Throwable $e) {
                    Log::error('Error updating store balance: ' . $e->getMessage());
                }
                break;
            case 'pending':
                $transaction->update(['payment_status' => 'unpaid']);
                break;
            case 'deny':
                $transaction->update(['payment_status' => 'failed']);
                $this->transactionRepository->restoreStock($transaction);
                break;
            case 'expire':
                $transaction->update(['payment_status' => 'failed']);
                $this->transactionRepository->restoreStock($transaction);
                break;
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
}

