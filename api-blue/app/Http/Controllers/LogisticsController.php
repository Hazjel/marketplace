<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
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
        // Authenticate webhook caller via pre-shared secret
        $secret = config('app.logistics_webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
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

        if (!$transaction) {
            return response()->json(['message' => 'AWB Not Found matched with any transaction'], 404);
        }

        // 3. Update Status Logic
        try {
            $previousStatus = $transaction->delivery_status;
            $newStatus = $previousStatus;

            switch ($status) {
                case 'ON_PROCESS':
                case 'MANIFESTED':
                case 'ON_DELIVERY':
                    $newStatus = 'delivering';
                    break;

                case 'DELIVERED':
                    $newStatus = 'completed';
                    break;

                case 'RETURNED':
                    // Optional: Handle return logic
                    break;

                default:
                    Log::warning("Unknown logistics status: $status for AWB: $awb");
                    return response()->json(['message' => "Status '$status' ignored"], 200);
            }

            // Save only if status changed
            if ($previousStatus !== $newStatus) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $newStatus, $status, $request) {
                    $transaction->delivery_status = $newStatus;

                    if ($status === 'DELIVERED' && $request->has('pod_receiver')) {
                        Log::info("Recipient: " . $request->pod_receiver);
                    }

                    $transaction->save();
                    Log::info("Transaction {$transaction->code} updated to {$transaction->delivery_status}");
                });
            }

            return ResponseHelper::jsonResponse(true, 'Webhook Processed Successfully', [
                'transaction_code' => $transaction->code,
                'new_status' => $newStatus
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing logistics webhook: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
