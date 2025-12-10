<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Repositories\StoreBalanceRepository;

class MidtransController extends Controller
{
    public function callback(Request $request)
    {
        $serverKey = config('midtrans.server_key');
        $hashedKey = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if ($hashedKey != $request->signature_key) {
            return response()->json(['message' => 'Invalid signature key'], 403);
        }

        $transactionStatus = $request->transaction_status;
        $transactionCode = $request->order_id;
        $transaction = Transaction::where('code', $transactionCode)->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        switch ($transactionStatus) {
            case 'capture':
                if ($request->payment_type == 'credit_card') {
                    if ($request->fraud_status == 'challenge') {
                        $transaction->update(['payment_status' => 'unpaid']);
                    } else {
                        $transaction->update(['payment_status' => 'paid']);

                        $store = Store::find($transaction->store_id);

                        $storeBalanceRepository = new StoreBalanceRepository;
                        $storeBalanceRepository->credit($store->storeBalance->id, $transaction->grand_total -  $transaction->shipping_cost);

                        $storeBalanceHistoryRepository = new StoreBalanceRepository;
                        $storeBalanceHistoryRepository->create([
                            'type' => 'income',
                            'reference_id' => $transaction->id,
                            'reference_type' => Transaction::class,
                            'amount' => $transaction->grand_total -  $transaction->shipping_cost,
                            'remarks' => 'Payment received ',
                        ]);
                    }
                }
                break;

            case 'settlement':
                $transaction->update(['payment_status' => 'paid']);

                $store = Store::find($transaction->store_id);

                $storeBalanceRepository = new StoreBalanceRepository;
                $storeBalanceRepository->credit($store->storeBalance->id, $transaction->grand_total -  $transaction->shipping_cost);
                
                $storeBalanceHistoryRepository = new StoreBalanceRepository;
                $storeBalanceHistoryRepository->create([
                    'type' => 'income',
                    'reference_id' => $transaction->id,
                    'reference_type' => Transaction::class,
                    'amount' => $transaction->grand_total -  $transaction->shipping_cost,
                    'remarks' => 'Payment received ',
                ]);
                break;
            case 'pending':
                $transaction->update(['payment_status' => 'unpaid']);
                break;
            case 'deny':
                $transaction->update(['payment_status' => 'failed']);
                break;
            case 'expire':
                $transaction->update(['payment_status' => 'failed']);
                break;
            case 'cancel':
                $transaction->update(['payment_status' => 'failed']);
                break;
            default:
                $transaction->update(['payment_status' => 'failed']);
                break;

                return response()->json(['message' => 'Payment Status updated successfully'], 200);
            
        }

    }
}
