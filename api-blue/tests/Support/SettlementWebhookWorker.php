<?php

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Transaction;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$app->instance(PaymentGatewayInterface::class, new class implements PaymentGatewayInterface
{
    public function getSnapToken(Transaction $transaction): ?string
    {
        return $transaction->snap_token;
    }

    public function refund(Transaction $transaction, string $reason): string
    {
        return self::REFUND_DONE;
    }

    public function expirePayment(string $orderId): void {}
});

$orderId = $argv[1] ?? '';
$amount = $argv[2] ?? '0.00';
$barrier = (int) ($argv[3] ?? 0);

try {
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    DB::select('select pg_advisory_unlock_shared(?)', [$barrier]);
    $request = Request::create('/api/midtrans-callback', 'POST', [
        'order_id' => $orderId,
        'status_code' => '200',
        'gross_amount' => $amount,
        'signature_key' => hash('sha512', $orderId.'200'.$amount.config('midtrans.serverKey')),
        'transaction_status' => 'settlement',
    ], [], [], ['HTTP_ACCEPT' => 'application/json']);
    echo $app->make(HttpKernel::class)->handle($request)->getStatusCode();
} catch (Throwable) {
    echo '500';
}
