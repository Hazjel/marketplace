<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Counters for events that only exist as a log line (a rejected Midtrans
 * webhook, an API 500, a crash in a buyer's browser), so `ops:check` can
 * alert on them. Recording must never break the request that hit the
 * problem.
 */
final class OpsSignals
{
    public const MIDTRANS_REJECTED = 'midtrans_rejected';

    public const SERVER_ERROR = 'server_error';

    public const CLIENT_ERROR = 'client_error';

    // Distinct samples kept per signal; the email shows the most frequent.
    private const MAX_SAMPLES = 20;

    /**
     * @param  string|null  $sample  what happened, e.g. "POST api/transaction 500";
     *                               identical samples are counted together
     */
    public static function record(string $signal, ?string $sample = null): void
    {
        try {
            Cache::add(self::key($signal), 0, now()->addDay());
            Cache::increment(self::key($signal));

            if ($sample !== null) {
                $samples = Cache::get(self::samplesKey($signal), []);
                if (isset($samples[$sample]) || count($samples) < self::MAX_SAMPLES) {
                    $samples[$sample] = ($samples[$sample] ?? 0) + 1;
                    Cache::put(self::samplesKey($signal), $samples, now()->addDay());
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function count(string $signal): int
    {
        return (int) Cache::get(self::key($signal), 0);
    }

    /**
     * Recorded samples, most frequent first.
     *
     * @return array<string, int>
     */
    public static function samples(string $signal): array
    {
        $samples = Cache::get(self::samplesKey($signal), []);
        arsort($samples);

        return $samples;
    }

    /**
     * Count since the previous pull, resetting it and its samples.
     */
    public static function pull(string $signal): int
    {
        Cache::forget(self::samplesKey($signal));

        return (int) Cache::pull(self::key($signal), 0);
    }

    private static function key(string $signal): string
    {
        return 'ops:signal:'.$signal;
    }

    private static function samplesKey(string $signal): string
    {
        return 'ops:signal:'.$signal.':samples';
    }
}
