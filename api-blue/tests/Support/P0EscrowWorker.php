<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::connection()->getPdo();

$action = $argv[1] ?? '';
$transactionId = $argv[2] ?? '';
$barrier = (int) ($argv[3] ?? 0);

try {
    Illuminate\Support\Facades\DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    Illuminate\Support\Facades\DB::select('select pg_advisory_unlock_shared(?)', [$barrier]);

    $repository = app(App\Interfaces\TransactionRepositoryInterface::class);
    $result = match ($action) {
        'complete' => $repository->completeTransaction($transactionId),
        'cancel' => $repository->cancelPaidOrder($transactionId, 'concurrent test'),
        'release' => app(App\Interfaces\EscrowRepositoryInterface::class)
            ->release(App\Models\Transaction::findOrFail($transactionId)),
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
