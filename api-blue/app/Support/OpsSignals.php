<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Counters for events that only exist as a log line (a rejected Midtrans
 * webhook leaves no row anywhere), so `ops:check` can alert on them.
 * Recording must never break the request that hit the problem.
 */
final class OpsSignals
{
    public const MIDTRANS_REJECTED = 'midtrans_rejected';

    public static function record(string $signal): void
    {
        try {
            Cache::add(self::key($signal), 0, now()->addDay());
            Cache::increment(self::key($signal));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function count(string $signal): int
    {
        return (int) Cache::get(self::key($signal), 0);
    }

    /**
     * Count since the previous pull, resetting it.
     */
    public static function pull(string $signal): int
    {
        return (int) Cache::pull(self::key($signal), 0);
    }

    private static function key(string $signal): string
    {
        return 'ops:signal:'.$signal;
    }
}
