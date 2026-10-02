<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('transaction:check-expiry')->everyMinute();

Schedule::command('transaction:auto-complete')->dailyAt('02:00');

// Operational alerts (config/ops.php). The heartbeat proves a queue worker
// is consuming jobs; ops:check emails OPS_ALERT_EMAIL when something breaks.
Schedule::job(new QueueHeartbeatJob)->everyMinute();
Schedule::command('ops:check')->everyFiveMinutes()->withoutOverlapping();
