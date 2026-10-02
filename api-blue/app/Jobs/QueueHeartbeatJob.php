<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Queued by the scheduler every minute. When a worker runs it, the queue is
 * alive; `ops:check` alerts when the timestamp stops moving, because then
 * refunds, verification emails and AI replies silently pile up.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const CACHE_KEY = 'ops:queue-heartbeat';

    // A missed beat is replaced by the next one a minute later.
    public int $tries = 1;

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, now()->getTimestamp(), now()->addDay());
    }
}
