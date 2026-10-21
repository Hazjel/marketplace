<?php

namespace App\Services\E2E;

use App\Models\Transaction;
use App\Services\MidtransPaymentGateway;

class FakePaymentGateway extends MidtransPaymentGateway
{
    public function getPaymentStatus(Transaction $transaction): object
    {
        return (object) [
            'transaction_status' => match ($transaction->payment_status) {
                'paid' => 'settlement',
                'failed' => 'expire',
                default => 'pending',
            },
            'payment_type' => 'credit_card',
            'fraud_status' => 'accept',
            'gross_amount' => (string) Transaction::midtransTotal(Transaction::inPayment($transaction->paymentCode())->get())->minor(),
        ];
    }

    public function getSnapToken(Transaction $transaction): ?string
    {
        return 'e2e-snap-'.$transaction->getKey();
    }

    public function refund(Transaction $transaction, string $reason): string
    {
        return self::REFUND_DONE;
    }
}
