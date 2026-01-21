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
        // 1. Log Payload (For Debugging)
        Log::info('LOGISTICS WEBHOOK RECEIVED:', $request->all());

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
            
            switch ($status) {
                case 'ON_PROCESS':
                case 'MANIFESTED':
                case 'ON_DELIVERY':
                    $transaction->delivery_status = 'delivering';
                    break;

                case 'DELIVERED':
                    $transaction->delivery_status = 'completed';
                    break;

                case 'RETURNED':
                    // Optional: Handle return logic
                    break;
                
                default:
                    // Unknown status, ignore or log
                    Log::warning("Unknown logistics status: $status for AWB: $awb");
                    return response()->json(['message' => "Status '$status' ignored"], 200);
            }

            // Save only if status changed
            if ($previousStatus !== $transaction->delivery_status) {
                if ($status === 'DELIVERED') {
                    // Save Delivery Proof info if available in webhook
                     if ($request->has('pod_receiver')) {
                        // We might store this in a 'notes' column or 'delivery_proof' if it was a text field, 
                        // but 'delivery_proof' is currently widely used for images. 
                        // For now, let's just log it or maybe assume we don't save text proof yet.
                        Log::info("Recipient: " . $request->pod_receiver);
                    }
                }
                
                $transaction->save();
                Log::info("Transaction {$transaction->code} updated to {$transaction->delivery_status}");
            }

            return ResponseHelper::jsonResponse(true, 'Webhook Processed Successfully', [
                'transaction_code' => $transaction->code,
                'new_status' => $transaction->delivery_status
            ], 200);

        } catch (\Exception $e) {
             Log::error('Error processing logistics webhook: ' . $e->getMessage());
             return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
