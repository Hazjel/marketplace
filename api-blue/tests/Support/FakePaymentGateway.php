<?php

namespace Tests\Support;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Transaction;

/**
 * Checkout cancels orders whose Snap token cannot be created, so tests that
 * check out need a gateway that answers instead of the real Midtrans.
 */
class FakePaymentGateway implements PaymentGatewayInterface
{
    public function getSnapToken(Transaction $transaction): ?string
    {
        return 'fake-snap-token';
    }

    public function refund(Transaction $transaction, string $reason): string
    {
        return self::REFUND_DONE;
    }
}
