<?php

namespace App\Http\Controllers;

use App\Events\TransactionStatusUpdated;
use App\Helpers\ResponseHelper;
use App\Models\Complaint;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogisticsController extends Controller
{
    /**
     * Handle Logistics Webhook (Simulation)
     * Payload expected:
     * {
     *    "awb": "JNE-123",
     *    "status": "DELIVERED", (ON_PROCESS, DELIVERED, RETURNED)
     *    "pod_receiver": "Budi", (Optional)
     *    "pod_date": "2024-01-01 12:00:00" (Optional)
     * }
     */
    public function webhook(Request $request)
    {
        // Pre-shared secret, required: without one configured the endpoint is
        // closed, since any caller knowing an AWB could move orders.
        $secret = (string) config('services.logistics.webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Webhook-Secret'))) {
            Log::warning('Logistics webhook unauthorized', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Log::info('Logistics webhook received', ['awb' => $request->awb, 'status' => $request->status]);

        $request->validate([
            'awb' => 'required|string',
            'status' => 'required|string',
        ]);

        $awb = $request->awb;
        $status = strtoupper($request->status);

        // 2. Find Transaction by Tracking Number
        $transaction = Transaction::where('tracking_number', $awb)->first();

        if (! $transaction) {
            return response()->json(['message' => 'AWB Not Found matched with any transaction'], 404);
        }

        // 3. Update Status Logic
        try {
            switch ($status) {
                case 'ON_PROCESS':
                case 'MANIFESTED':
                case 'ON_DELIVERY':
                    break;

                case 'DELIVERED':
                    // Never completes the order: completion releases escrow and
                    // ends the buyer's complaint window, so only the buyer
                    // (completeTransaction) or transaction:auto-complete do it.
                    Log::info('Logistics: paket diterima', [
                        'transaction' => $transaction->code,
                        'awb' => $awb,
                        'pod_receiver' => $request->input('pod_receiver'),
                        'pod_date' => $request->input('pod_date'),
                    ]);

                    return $this->processed($transaction->code, $transaction->delivery_status);

                case 'RETURNED':
                    Log::warning("Logistics: paket dikembalikan untuk AWB {$awb}", ['transaction' => $transaction->code]);

                    return $this->processed($transaction->code, $transaction->delivery_status);

                default:
                    Log::warning("Unknown logistics status: $status for AWB: $awb");

                    return response()->json(['message' => "Status '$status' ignored"], 200);
            }

            $updated = DB::transaction(function () use ($transaction) {
                $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

                // Only a paid order not yet shipped moves forward; a complained,
                // completed or cancelled order is left alone.
                if (! $locked
                    || $locked->payment_status !== 'paid'
                    || ! in_array($locked->delivery_status, ['pending', 'processing'], true)
                    || Complaint::activeFor($locked->id)) {
                    return null;
                }

                $locked->delivery_status = 'delivering';
                $locked->save();

                return $locked;
            });

            if (! $updated) {
                return $this->processed($transaction->code, $transaction->fresh()?->delivery_status);
            }

            Log::info("Transaction {$updated->code} updated to delivering");
            event(new TransactionStatusUpdated($updated->fresh()));

            return $this->processed($updated->code, 'delivering');
        } catch (\Exception $e) {
            Log::error('Error processing logistics webhook: '.$e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    private function processed(string $code, ?string $status)
    {
        return ResponseHelper::jsonResponse(true, 'Webhook Processed Successfully', [
            'transaction_code' => $code,
            'new_status' => $status,
        ], 200);
    }
}
