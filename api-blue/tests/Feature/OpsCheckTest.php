<?php

namespace Tests\Feature;

use App\Console\Commands\OpsCheck;
use App\Jobs\QueueHeartbeatJob;
use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\OpsAlertNotification;
use App\Repositories\BuyerBalanceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OpsCheckTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'ops@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ops.alert_email' => self::EMAIL]);
        Notification::fake();
        // A healthy worker unless a test says otherwise.
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->getTimestamp());
        // ...and last night's backup went through.
        Cache::put(OpsCheck::BACKUP_DONE_AT, now()->subHours(5)->getTimestamp());
    }

    /**
     * Titles of the problems mailed so far, in order.
     *
     * @return list<string>
     */
    private function alertedTitles(): array
    {
        return Notification::sent(new AnonymousNotifiable, OpsAlertNotification::class)
            ->flatMap(fn (OpsAlertNotification $n) => array_column($n->problems, 'title'))
            ->values()
            ->all();
    }

    private function failJob(string $displayName, string $error): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => $displayName]),
            'exception' => $error."\n#0 stack trace",
            'failed_at' => now(),
        ]);
    }

    private function transaction(array $attributes): Transaction
    {
        $user = User::factory()->create();
        $store = Store::create([
            'user_id' => $user->id,
            'name' => 'Ops Store '.Str::random(4),
            'username' => 'ops'.Str::lower(Str::random(6)),
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '0812',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
        ]);
        $buyer = Buyer::create(['user_id' => User::factory()->create()->id, 'phone_number' => '0813']);

        return Transaction::create(array_merge([
            'code' => 'OPS_'.Str::upper(Str::random(8)),
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'tax' => 0,
            'grand_total' => 216000,
            'payment_status' => 'failed',
            'delivery_status' => 'cancelled',
        ], $attributes));
    }

    public function test_nothing_is_sent_when_everything_is_healthy(): void
    {
        $this->artisan('ops:check')->assertSuccessful();
        $this->artisan('ops:check')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_heartbeat_job_records_that_the_queue_is_alive(): void
    {
        Cache::forget(QueueHeartbeatJob::CACHE_KEY);

        (new QueueHeartbeatJob)->handle();

        $this->assertEqualsWithDelta(now()->getTimestamp(), Cache::get(QueueHeartbeatJob::CACHE_KEY), 2);
    }

    public function test_backup_done_records_a_fresh_backup(): void
    {
        Cache::forget(OpsCheck::BACKUP_DONE_AT);

        $this->artisan('ops:backup-done')->assertSuccessful();

        $this->assertEqualsWithDelta(now()->getTimestamp(), Cache::get(OpsCheck::BACKUP_DONE_AT), 2);
    }

    public function test_a_stale_backup_alerts(): void
    {
        Cache::put(OpsCheck::BACKUP_DONE_AT, now()->subDays(8)->getTimestamp());

        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['Backup database tidak berjalan'], $this->alertedTitles());
    }

    public function test_no_backup_ever_alerts_once_a_day_not_every_hour(): void
    {
        // A cron that was never installed is as silent as one that broke.
        Cache::forget(OpsCheck::BACKUP_DONE_AT);

        $this->artisan('ops:check')->assertSuccessful();
        $this->travel(2)->hours();
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->getTimestamp());
        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['Backup database tidak berjalan'], $this->alertedTitles());
    }

    public function test_each_problem_in_the_email_states_its_own_cooldown(): void
    {
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->subMinutes(15)->getTimestamp());
        Cache::forget(OpsCheck::BACKUP_DONE_AT);

        $this->artisan('ops:check')->assertSuccessful();

        Notification::assertSentOnDemand(OpsAlertNotification::class, function (OpsAlertNotification $n) {
            $lines = $n->toMail(new AnonymousNotifiable)->introLines;

            return in_array('_Tidak dikirim ulang sebelum 60 menit._', $lines, true)
                && in_array('_Tidak dikirim ulang sebelum 24 jam._', $lines, true);
        });
    }

    public function test_a_stale_heartbeat_alerts_once_per_cooldown(): void
    {
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->subMinutes(15)->getTimestamp());

        $this->artisan('ops:check')->assertSuccessful();
        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['Queue worker tidak memproses job'], $this->alertedTitles());
        Notification::assertSentOnDemand(
            OpsAlertNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === self::EMAIL
        );

        // The outage lasts: the next reminder comes after the cooldown.
        $this->travel(61)->minutes();
        $this->artisan('ops:check')->assertSuccessful();
        $this->assertCount(2, $this->alertedTitles());
    }

    public function test_a_missing_heartbeat_right_after_deploy_is_not_an_outage_yet(): void
    {
        Cache::forget(QueueHeartbeatJob::CACHE_KEY);

        $this->artisan('ops:check')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(11)->minutes();
        $this->artisan('ops:check')->assertSuccessful();
        $this->assertSame(['Queue worker tidak memproses job'], $this->alertedTitles());
    }

    public function test_new_failed_jobs_are_reported_once(): void
    {
        $this->failJob('App\\Jobs\\Old', 'Before the first check');
        $this->travel(1)->seconds();
        // The first run only sets the starting point.
        $this->artisan('ops:check')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(1)->seconds();
        $this->failJob('App\\Jobs\\RefundCancelledTransactionJob', 'Midtrans API is returning API error');
        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['1 job antrean gagal permanen'], $this->alertedTitles());
        Notification::assertSentOnDemand(OpsAlertNotification::class, function (OpsAlertNotification $n) {
            $lines = implode(' ', $n->problems[0]['lines']);

            return str_contains($lines, 'RefundCancelledTransactionJob: 1x')
                && str_contains($lines, 'Midtrans API is returning API error')
                && ! str_contains($lines, 'Old');
        });

        // Already reported: no repeat after the cooldown.
        $this->travel(61)->minutes();
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->getTimestamp());
        $this->artisan('ops:check')->assertSuccessful();
        $this->assertCount(1, $this->alertedTitles());
    }

    public function test_rejected_midtrans_webhooks_are_reported_and_reset(): void
    {
        $this->postJson('/api/midtrans-callback', [
            'order_id' => 'FORGED',
            'status_code' => '200',
            'gross_amount' => '1000.00',
            'signature_key' => 'not-a-valid-signature',
            'transaction_status' => 'settlement',
        ])->assertStatus(403);

        $this->artisan('ops:check')->assertSuccessful();
        $this->assertSame(['Notifikasi Midtrans ditolak'], $this->alertedTitles());

        // The counter was consumed by the alert.
        $this->travel(61)->minutes();
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->getTimestamp());
        $this->artisan('ops:check')->assertSuccessful();
        $this->assertCount(1, $this->alertedTitles());
    }

    public function test_refunds_waiting_on_a_manual_transfer_are_reminded_daily(): void
    {
        $this->transaction([
            'refund_status' => 'manual_required',
            'refund_amount' => 216000,
            'refund_account_number' => '1234567890',
        ]);
        // No account yet: the buyer has to act, not the admin.
        $this->transaction(['refund_status' => 'manual_required', 'refund_amount' => 50000]);

        $this->artisan('ops:check')->assertSuccessful();
        $this->assertSame(['Refund manual menunggu transfer'], $this->alertedTitles());
        Notification::assertSentOnDemand(
            OpsAlertNotification::class,
            fn (OpsAlertNotification $n) => str_contains($n->problems[0]['lines'][0], '1 pembeli sudah mengisi rekening, total Rp216.000')
        );

        $this->travel(2)->hours();
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->getTimestamp());
        $this->artisan('ops:check')->assertSuccessful();
        $this->assertCount(1, $this->alertedTitles());

        $this->travel(1)->days();
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->getTimestamp());
        Cache::put(OpsCheck::BACKUP_DONE_AT, now()->getTimestamp());
        $this->artisan('ops:check')->assertSuccessful();
        $this->assertCount(2, $this->alertedTitles());
    }

    public function test_a_refund_stuck_in_processing_is_reported(): void
    {
        $stuck = $this->transaction(['refund_status' => 'processing', 'refund_amount' => 216000]);
        $stuck->forceFill(['updated_at' => now()->subHours(49)])->saveQuietly();
        $this->transaction(['refund_status' => 'processing', 'refund_amount' => 1000]);

        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['Refund otomatis macet'], $this->alertedTitles());
        Notification::assertSentOnDemand(
            OpsAlertNotification::class,
            fn (OpsAlertNotification $n) => str_contains($n->problems[0]['lines'][0], '1 refund masih "processing"')
                && str_contains($n->problems[0]['lines'][0], $stuck->code)
        );
    }

    public function test_api_server_errors_are_reported_with_their_route(): void
    {
        Route::get('api/_test/boom/{id}', fn () => throw new RuntimeException('boom'));

        $this->getJson('/api/_test/boom/1')->assertStatus(500);
        $this->getJson('/api/_test/boom/2')->assertStatus(500);
        // 4xx is the client's problem, not a server error.
        $this->getJson('/api/product/does-not-exist-'.Str::random(6))->assertStatus(404);

        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['Error server (HTTP 5xx) di API'], $this->alertedTitles());
        Notification::assertSentOnDemand(OpsAlertNotification::class, function (OpsAlertNotification $n) {
            $lines = $n->problems[0]['lines'];

            return str_starts_with($lines[0], '2 request gagal')
                && in_array('- GET api/_test/boom/{id} 500 (2x)', $lines, true);
        });
    }

    public function test_web_errors_are_reported_once_a_few_arrive(): void
    {
        $report = fn (string $message) => $this->postJson('/api/client-errors', [
            'source' => 'web',
            'message' => $message,
            'url' => 'https://blukios.store/auth/reset-password?token=secret-token&email=a@b.c',
            'stack' => "TypeError: x\n    at setup (Checkout.vue:10)",
        ])->assertNoContent();

        $report("TypeError: Cannot read properties of undefined (reading 'id')");
        $report("TypeError: Cannot read properties of undefined (reading 'id')");

        // Below the threshold of 3: nothing yet.
        $this->artisan('ops:check')->assertSuccessful();
        Notification::assertNothingSent();

        $report('ChunkLoadError: Loading chunk 12 failed');
        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['Error di aplikasi pengguna (browser/mobile)'], $this->alertedTitles());
        Notification::assertSentOnDemand(OpsAlertNotification::class, function (OpsAlertNotification $n) {
            $text = implode("\n", $n->problems[0]['lines']);

            return str_contains($text, "- web: TypeError: Cannot read properties of undefined (reading 'id') @ /auth/reset-password (2x)")
                // The query string (reset token, email) is never kept.
                && ! str_contains($text, 'secret-token');
        });
    }

    public function test_client_error_reports_are_validated(): void
    {
        $this->postJson('/api/client-errors', ['source' => 'desktop', 'message' => 'x'])->assertStatus(422);
        $this->postJson('/api/client-errors', ['source' => 'web'])->assertStatus(422);
        $this->postJson('/api/client-errors', ['source' => 'web', 'message' => str_repeat('x', 501)])->assertStatus(422);
    }

    public function test_without_a_recipient_problems_are_only_logged(): void
    {
        config(['ops.alert_email' => null]);
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->subMinutes(15)->getTimestamp());

        $this->artisan('ops:check')
            ->expectsOutputToContain('OPS_ALERT_EMAIL kosong')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_saldo_blukios_kept_in_step_with_its_history_is_not_reported(): void
    {
        $buyer = Buyer::create(['user_id' => User::factory()->create()->id, 'phone_number' => '0813']);
        $repository = new BuyerBalanceRepository;
        $repository->credit($buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:ops-1');
        $repository->debit($buyer->id, '20000', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:ops-2');

        $this->artisan('ops:check')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_saldo_blukios_that_drifted_from_its_history_is_reported(): void
    {
        $buyer = Buyer::create(['user_id' => User::factory()->create()->id, 'phone_number' => '0813']);
        (new BuyerBalanceRepository)->credit($buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:ops-1');
        // A balance edited behind the ledger's back, and one with no history at all.
        DB::table('buyers')->where('id', $buyer->id)->update(['balance' => 55000]);
        $orphan = Buyer::create(['user_id' => User::factory()->create()->id, 'phone_number' => '0813']);
        DB::table('buyers')->where('id', $orphan->id)->update(['balance' => 1000]);

        $this->artisan('ops:check')->assertSuccessful();

        $this->assertSame(['Saldo Blukios tidak cocok dengan riwayat'], $this->alertedTitles());
        Notification::assertSentOnDemand(
            OpsAlertNotification::class,
            fn (OpsAlertNotification $n) => str_contains($n->problems[0]['lines'][0], '2 pembeli')
                && str_contains($n->problems[0]['lines'][0], $buyer->id.' (Rp5.000)')
                && str_contains($n->problems[0]['lines'][0], $orphan->id.' (Rp1.000)')
        );
    }
}
