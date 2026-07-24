<?php

namespace App\Http\Controllers;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use App\Services\MidtransPaymentStatusInterpreter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    protected $transactionRepository;

    protected EscrowRepositoryInterface $escrowRepository;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        EscrowRepositoryInterface $escrowRepository
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->escrowRepository = $escrowRepository;
    }

    public function callback(Request $request)
    {
        // load server key from config (matches config/midtrans.php)
        $serverKey = config('midtrans.serverKey');

        // compute signature using Midtrans formula: order_id + status_code + gross_amount + server_key
        $hashedKey = hash('sha512', ($request->order_id ?? '').($request->status_code ?? '').($request->gross_amount ?? '').($serverKey ?? ''));

        Log::info('Midtrans callback received', [
            'order_id' => $request->order_id ?? null,
            'status_code' => $request->status_code ?? null,
            'transaction_status' => $request->transaction_status ?? null,
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

        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        // Defense-in-depth: verify amount matches DB (signature already covers this)
        $expectedAmount = (int) round((float) $transaction->grand_total);
        $receivedAmount = (int) round((float) ($request->gross_amount ?? 0));
        if ($expectedAmount !== $receivedAmount) {
            Log::error('Midtrans amount mismatch', [
                'expected' => $expectedAmount,
                'received' => $receivedAmount,
                'transaction' => $transactionCode,
            ]);

            return response()->json(['message' => 'Amount mismatch'], 403);
        }

        $newStatus = MidtransPaymentStatusInterpreter::interpret(
            $transactionStatus,
            $request->payment_type,
            $request->fraud_status
        );

        if ($newStatus === null) {
            // no-op (mis. capture non-credit_card)
        } elseif ($newStatus === 'paid') {
            // Guard: skip if already paid (prevents double-credit on duplicate webhook)
            if ($transaction->payment_status === 'paid') {
                Log::info('Duplicate webhook ignored for: '.$transactionCode);
            } else {
                $transaction->update(['payment_status' => 'paid']);
                $this->escrowRepository->credit($transaction);
            }
        } elseif ($newStatus === 'unpaid') {
            $transaction->update(['payment_status' => 'unpaid']);
        } elseif ($newStatus === 'failed') {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['payment_status' => 'failed']);
                $this->transactionRepository->restoreStock($transaction);
            });
        }

        // always return 200 after processing so Midtrans considers callback successful
        return response()->json(['message' => 'Payment Status updated successfully'], 200);
    }
}
