<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$transactionId = $argv[1] ?? '';
$token = $argv[2] ?? '';
$barrier = (int) ($argv[3] ?? 0);

try {
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    DB::select('select pg_advisory_unlock_shared(?)', [$barrier]);

    $request = Request::create(
        '/api/transaction/'.$transactionId.'/cancel-unpaid',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ],
    );
    $response = $app->make(Kernel::class)->handle($request);
    echo $response->getStatusCode();
} catch (Throwable) {
    echo 500;
}
