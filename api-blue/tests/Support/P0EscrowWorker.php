<?php

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DB::connection()->getPdo();

$action = $argv[1] ?? '';
$transactionId = $argv[2] ?? '';
$barrier = (int) ($argv[3] ?? 0);
// Deterministic scenarios (P0EscrowConcurrencyTest::runHeldFirst) pass a 4th
// argument: the worker prints "pid:<backend pid>" first, and with a hold key
// > 0 it also stops right after its first FOR UPDATE on transactions, prints
// "locked" and waits for the test to release that advisory lock before the
// real path goes on (and commits).
$hold = $argv[4] ?? null;

try {
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    DB::select('select pg_advisory_unlock_shared(?)', [$barrier]);

    if ($hold !== null) {
        echo 'pid:'.DB::selectOne('select pg_backend_pid() as pid')->pid.PHP_EOL;
    }
    if ((int) $hold > 0) {
        $holding = false;
        DB::listen(function (QueryExecuted $query) use (&$holding, $hold) {
            if ($holding || ! str_contains($query->sql, 'from "transactions"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $holding = true;
            echo 'locked'.PHP_EOL;
            DB::select('select pg_advisory_lock(?)', [(int) $hold]);
            DB::select('select pg_advisory_unlock(?)', [(int) $hold]);
        });
    }

    $repository = app(TransactionRepositoryInterface::class);
    $result = match ($action) {
        'complete' => $repository->completeTransaction($transactionId),
        'cancel' => $repository->cancelPaidOrder($transactionId, 'concurrent test'),
        'ship' => $repository->updateStatus($transactionId, ['delivery_status' => 'delivering', 'tracking_number' => 'JNE-RACE']),
        'release' => app(EscrowRepositoryInterface::class)
            ->release(Transaction::findOrFail($transactionId)),
        default => throw new InvalidArgumentException('Unknown action'),
    };

    echo json_encode(['ok' => true, 'id' => $result->id ?? null], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'code' => $e->getCode(),
        'exception' => $e::class,
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
