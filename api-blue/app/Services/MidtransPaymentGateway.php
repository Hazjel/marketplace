<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransPaymentGateway implements PaymentGatewayInterface
{
    public function getSnapToken(Transaction $transaction): ?string
    {
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

        try {
            $snapToken = Snap::getSnapToken($params);

            Log::info('Snap token generated:', ['token' => $snapToken, 'transaction' => $transaction->code]);

            return $snapToken;
        } catch (\Throwable $e) {
            Log::error('Midtrans snap token failed: '.$e->getMessage(), [
                'transaction' => $transaction->code,
            ]);

            return null;
        }
    }
}
