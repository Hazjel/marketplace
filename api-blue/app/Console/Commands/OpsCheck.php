<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeatJob;
use App\Models\Transaction;
use App\Notifications\OpsAlertNotification;
use App\Support\OpsSignals;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks the paths that fail silently and emails OPS_ALERT_EMAIL.
 *
 * Each problem has a cooldown, so a lasting outage mails once an hour, not
 * every 5 minutes. Event-type problems (failed jobs, rejected webhooks) are
 * only marked as reported once an email actually went out, so whatever
 * happens during a cooldown is included in the next email.
 */
class OpsCheck extends Command
{
    protected $signature = 'ops:check';

    protected $description = 'Check the queue worker, failed jobs, rejected Midtrans webhooks and refunds; email OPS_ALERT_EMAIL about problems';

    private const FAILED_JOBS_CURSOR = 'ops:failed-jobs-cursor';

    private const HEARTBEAT_MISSING_SINCE = 'ops:queue-heartbeat-missing-since';

    /** Unix time of the last successful scripts/backup-db.sh run, set by `ops:backup-done`. */
    public const BACKUP_DONE_AT = 'ops:backup-done-at';

    public function handle(): int
    {
        $problems = array_values(array_filter([
            $this->queueWorker(),
            $this->backup(),
            $this->serverErrors(),
            $this->clientErrors(),
            $this->failedJobs(),
            $this->rejectedWebhooks(),
            $this->stuckRefunds(),
            $this->manualRefunds(),
        ]));

        $due = array_values(array_filter(
            $problems,
            fn (array $problem) => Cache::add(
                'ops:alerted:'.$problem['key'],
                true,
                now()->addMinutes($problem['cooldown'])
            )
        ));

        if ($due === []) {
            $this->info(count($problems).' masalah, tidak ada yang perlu dikirim sekarang.');

            return self::SUCCESS;
        }

        foreach ($due as $problem) {
            Log::warning('ops:check '.$problem['title'], ['lines' => $problem['lines']]);
        }

        $email = config('ops.alert_email');
        if (! $email) {
            $this->warn('OPS_ALERT_EMAIL kosong: '.count($due).' masalah hanya dicatat di log.');
            $this->acknowledge($due);

            return self::SUCCESS;
        }

        try {
            Notification::route('mail', $email)->notifyNow(new OpsAlertNotification(
                array_map(fn (array $p) => ['title' => $p['title'], 'lines' => $p['lines'], 'cooldown' => $p['cooldown']], $due)
            ));
        } catch (Throwable $e) {
            // Retry on the next run instead of going quiet for an hour.
            foreach ($due as $problem) {
                Cache::forget('ops:alerted:'.$problem['key']);
            }
            report($e);
            $this->error('Email alert gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->acknowledge($due);
        $this->info(count($due).' masalah dikirim ke '.$email.'.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $due
     */
    private function acknowledge(array $due): void
    {
        foreach ($due as $problem) {
            if (($problem['acknowledge'] ?? null) instanceof Closure) {
                $problem['acknowledge']();
            }
        }
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, mixed>
     */
    private function problem(string $key, string $title, array $lines, ?int $cooldown = null, ?Closure $acknowledge = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'lines' => $lines,
            'cooldown' => $cooldown ?? (int) config('ops.alert_cooldown_minutes'),
            'acknowledge' => $acknowledge,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function queueWorker(): ?array
    {
        $beat = Cache::get(QueueHeartbeatJob::CACHE_KEY);

        if ($beat === null) {
            // Right after a deploy there is no beat yet; only count from
            // the first check that noticed.
            Cache::add(self::HEARTBEAT_MISSING_SINCE, now()->getTimestamp(), now()->addDay());
            $since = (int) Cache::get(self::HEARTBEAT_MISSING_SINCE);
        } else {
            Cache::forget(self::HEARTBEAT_MISSING_SINCE);
            $since = (int) $beat;
        }

        $minutes = intdiv(now()->getTimestamp() - $since, 60);
        if ($minutes < (int) config('ops.queue_stale_minutes')) {
            return null;
        }

        return $this->problem('queue-worker', 'Queue worker tidak memproses job', [
            $beat === null
                ? "Belum ada heartbeat antrean selama {$minutes} menit."
                : "Heartbeat antrean terakhir {$minutes} menit lalu.",
            'Refund otomatis, email verifikasi/reset password, dan balasan AI tertahan sampai worker jalan lagi.',
            'Cek kontainer blue-queue: `docker ps -a | grep blue-queue` dan `docker logs --tail 50 blue-queue`.',
        ]);
    }

    /**
     * No record at all also alerts: a backup cron that was never installed
     * fails exactly as silently as one that broke.
     *
     * @return array<string, mixed>|null
     */
    private function backup(): ?array
    {
        $at = Cache::get(self::BACKUP_DONE_AT);
        $hours = $at === null ? null : intdiv(now()->getTimestamp() - (int) $at, 3600);
        if ($hours !== null && $hours < (int) config('ops.backup_stale_hours')) {
            return null;
        }

        return $this->problem('backup', 'Backup database tidak berjalan', [
            $hours === null
                ? 'Belum ada backup database yang tercatat berhasil.'
                : "Backup database terakhir berhasil {$hours} jam lalu.",
            'Tanpa backup, disk rusak atau query keliru menghapus transaksi, escrow, dan saldo penjual secara permanen.',
            'Cek di server: `tail -30 ~/backups/blukios/backup.log`. Cara pasang: README bagian "Backups".',
        ], 24 * 60);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serverErrors(): ?array
    {
        return $this->signalProblem(
            OpsSignals::SERVER_ERROR,
            (int) config('ops.server_error_threshold'),
            'Error server (HTTP 5xx) di API',
            fn (int $count) => "{$count} request gagal dengan error server sejak laporan terakhir. Dampaknya ke web dan aplikasi mobile.",
            'Detail: `docker exec blue-api sh -c \'grep -h "\"level_name\":\"ERROR\"" storage/logs/laravel-*.log | tail -20\'`. '
                .'Setiap baris punya request_id; semua log satu request: grep id itu.',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function clientErrors(): ?array
    {
        return $this->signalProblem(
            OpsSignals::CLIENT_ERROR,
            (int) config('ops.client_error_threshold'),
            'Error di aplikasi pengguna (browser/mobile)',
            fn (int $count) => "{$count} error JavaScript/aplikasi dilaporkan dari perangkat pengguna sejak laporan terakhir.",
            'Detail dan stack trace: `docker exec blue-api sh -c \'grep -h "Client error" storage/logs/laravel-*.log | tail -20\'`.',
        );
    }

    /**
     * A counted signal with its most frequent samples, e.g. "POST
     * api/transaction 500 (3x)". Reported once the count reaches $threshold;
     * the count resets only when an email went out.
     *
     * @param  Closure(int): string  $summary
     * @return array<string, mixed>|null
     */
    private function signalProblem(string $signal, int $threshold, string $title, Closure $summary, string $hint): ?array
    {
        $count = OpsSignals::count($signal);
        if ($count === 0 || $count < max(1, $threshold)) {
            return null;
        }

        $lines = [$summary($count)];
        foreach (array_slice(OpsSignals::samples($signal), 0, 5, true) as $sample => $times) {
            $lines[] = "- {$sample} ({$times}x)";
        }
        $lines[] = $hint;

        return $this->problem(
            str_replace('_', '-', $signal),
            $title,
            $lines,
            acknowledge: fn () => OpsSignals::pull($signal),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function failedJobs(): ?array
    {
        $table = config('queue.failed.table', 'failed_jobs');
        // First run: start from now, not from every failure ever recorded.
        $cursor = Cache::get(self::FAILED_JOBS_CURSOR);
        if ($cursor === null) {
            Cache::forever(self::FAILED_JOBS_CURSOR, now()->toDateTimeString());

            return null;
        }

        $rows = DB::table($table)
            ->where('failed_at', '>', $cursor)
            ->orderBy('failed_at')
            ->get(['payload', 'exception', 'failed_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $lines = $rows
            ->groupBy(fn ($row) => json_decode($row->payload, true)['displayName'] ?? 'unknown')
            ->map(function ($group, $job) {
                $reason = Str::limit(strtok((string) $group->last()->exception, "\n") ?: '', 200);

                return class_basename($job).': '.$group->count()."x gagal. Terakhir: {$reason}";
            })
            ->values()
            ->all();

        $lines[] = 'Detail: tabel failed_jobs, atau `php artisan queue:failed` di kontainer blue-api.';
        $newest = (string) $rows->last()->failed_at;

        return $this->problem(
            'failed-jobs',
            $rows->count().' job antrean gagal permanen',
            $lines,
            acknowledge: fn () => Cache::forever(self::FAILED_JOBS_CURSOR, $newest),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rejectedWebhooks(): ?array
    {
        $count = OpsSignals::count(OpsSignals::MIDTRANS_REJECTED);
        if ($count === 0) {
            return null;
        }

        return $this->problem(
            'midtrans-rejected',
            'Notifikasi Midtrans ditolak',
            [
                "{$count} notifikasi pembayaran ditolak karena signature atau nominalnya tidak cocok.",
                'Penyebab biasanya Server Key atau URL notifikasi di dashboard Midtrans tidak sesuai mode (sandbox/production). '
                    .'Pembayaran yang notifikasinya ditolak tidak tercatat lunas. Kalau konfigurasi benar, ada yang mengirim notifikasi palsu.',
            ],
            acknowledge: fn () => OpsSignals::pull(OpsSignals::MIDTRANS_REJECTED),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stuckRefunds(): ?array
    {
        $hours = (int) config('ops.refund_processing_max_hours');
        $stuck = Transaction::where('refund_status', 'processing')
            ->where('updated_at', '<', now()->subHours($hours))
            ->pluck('code');

        if ($stuck->isEmpty()) {
            return null;
        }

        return $this->problem('stuck-refunds', 'Refund otomatis macet', [
            $stuck->count()." refund masih \"processing\" lebih dari {$hours} jam: ".$stuck->take(10)->implode(', ').'.',
            'Job refund-nya hilang atau tidak pernah jalan. Cek status di dashboard Midtrans, lalu selesaikan manual.',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manualRefunds(): ?array
    {
        $ready = Transaction::where('refund_status', 'manual_required')
            ->whereNotNull('refund_account_number')
            ->orderBy('updated_at')
            ->get(['code', 'refund_amount']);

        if ($ready->isEmpty()) {
            return null;
        }

        $total = $ready->sum(fn ($t) => (float) $t->refund_amount);

        return $this->problem(
            'manual-refunds',
            'Refund manual menunggu transfer',
            [
                $ready->count().' pembeli sudah mengisi rekening, total Rp'.number_format($total, 0, ',', '.')
                    .': '.$ready->pluck('code')->take(10)->implode(', ').'.',
                'Transfer, lalu tandai di Admin > Semua Transaksi > tab "Menunggu Refund".',
            ],
            cooldown: (int) config('ops.manual_refund_reminder_minutes'),
        );
    }
}
