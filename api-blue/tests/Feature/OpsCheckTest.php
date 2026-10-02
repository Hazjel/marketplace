<?php

namespace Tests\Feature;

use App\Jobs\QueueHeartbeatJob;
use App\Models\Buyer;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\OpsAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

    public function test_without_a_recipient_problems_are_only_logged(): void
    {
        config(['ops.alert_email' => null]);
        Cache::put(QueueHeartbeatJob::CACHE_KEY, now()->subMinutes(15)->getTimestamp());

        $this->artisan('ops:check')
            ->expectsOutputToContain('OPS_ALERT_EMAIL kosong')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }
}
