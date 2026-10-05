<?php

namespace Tests\Feature;

use App\Models\Payout;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class IrisNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const DETAIL_URL = 'https://iris.test/iris/api/v1/payouts/ref-123';

    private Payout $payout;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.irisKey' => 'iris-key', 'midtrans.irisBaseUrl' => 'https://iris.test/iris']);

        $this->payout = Payout::create([
            'payable_type' => 'transaction',
            'payable_id' => (string) Str::uuid(),
            'reference_no' => 'ref-123',
            'amount' => '18100.00',
            'bank_code' => 'bca',
            'account_number' => '1234567890',
            'account_name' => 'Budi Santoso',
            'status' => 'queued',
        ]);
    }

    public function test_status_comes_from_iris_not_from_the_body(): void
    {
        Http::fake([self::DETAIL_URL => Http::response(['status' => 'approved', 'amount' => '18100.0'])]);

        $this->postJson('/api/iris/notification', ['reference_no' => 'ref-123', 'status' => 'completed', 'amount' => '1'])
            ->assertOk();

        $this->assertSame('approved', $this->payout->fresh()->status);
    }

    public function test_body_saying_completed_is_ignored_when_iris_says_failed(): void
    {
        Http::fake([self::DETAIL_URL => Http::response(['status' => 'failed', 'error_message' => 'Account closed'])]);

        $this->postJson('/api/iris/notification', ['reference_no' => 'ref-123', 'status' => 'completed'])
            ->assertOk();

        $payout = $this->payout->fresh();
        $this->assertSame('failed', $payout->status);
        $this->assertSame('Account closed', $payout->failure_reason);
    }

    public function test_unknown_reference_is_ignored(): void
    {
        Http::fake();

        $this->postJson('/api/iris/notification', ['reference_no' => 'nope', 'status' => 'completed'])
            ->assertOk();

        Http::assertNothingSent();
        $this->assertSame('queued', $this->payout->fresh()->status);
    }

    public function test_iris_down_returns_5xx_and_leaves_the_payout_unchanged(): void
    {
        Http::fake([self::DETAIL_URL => Http::response([], 503)]);

        $this->postJson('/api/iris/notification', ['reference_no' => 'ref-123', 'status' => 'completed'])
            ->assertStatus(503);

        $this->assertSame('queued', $this->payout->fresh()->status);
    }

    public function test_detail_without_status_returns_5xx(): void
    {
        Http::fake([self::DETAIL_URL => Http::response(['amount' => '18100.0'])]);

        $this->postJson('/api/iris/notification', ['reference_no' => 'ref-123'])->assertStatus(503);

        $this->assertSame('queued', $this->payout->fresh()->status);
    }

    public function test_non_string_or_empty_reference_is_ignored_without_lookup(): void
    {
        Http::fake();

        $this->postJson('/api/iris/notification', ['reference_no' => ['ref-123']])->assertOk();
        $this->postJson('/api/iris/notification', ['reference_no' => ''])->assertOk();
        $this->postJson('/api/iris/notification', [])->assertOk();

        Http::assertNothingSent();
        $this->assertSame('queued', $this->payout->fresh()->status);
    }

    public function test_terminal_status_is_never_overwritten(): void
    {
        $this->payout->update(['status' => 'completed']);
        Http::fake([self::DETAIL_URL => Http::response(['status' => 'processed'])]);

        $this->postJson('/api/iris/notification', ['reference_no' => 'ref-123'])->assertOk();

        $this->assertSame('completed', $this->payout->fresh()->status);
    }

    public function test_failed_payout_allows_a_new_attempt_but_not_two_active_ones(): void
    {
        $attempt = fn () => Payout::create([
            'payable_type' => $this->payout->payable_type,
            'payable_id' => $this->payout->payable_id,
            'amount' => '18100.00',
            'bank_code' => 'bca',
            'account_number' => '1234567890',
            'account_name' => 'Budi Santoso',
            'status' => 'creating',
        ]);

        try {
            $attempt();
            $this->fail('two active payouts for one payable must be rejected');
        } catch (UniqueConstraintViolationException) {
        }

        $this->payout->update(['status' => 'failed']);
        $this->assertSame('creating', $attempt()->status);
    }

    public function test_duplicate_notification_is_a_no_op(): void
    {
        Http::fake([self::DETAIL_URL => Http::response(['status' => 'completed'])]);

        $this->postJson('/api/iris/notification', ['reference_no' => 'ref-123'])->assertOk();
        $updatedAt = $this->payout->fresh()->updated_at;
        $this->travel(5)->minutes();
        $this->postJson('/api/iris/notification', ['reference_no' => 'ref-123'])->assertOk();

        $payout = $this->payout->fresh();
        $this->assertSame('completed', $payout->status);
        $this->assertEquals($updatedAt, $payout->updated_at);
    }

    public function test_account_number_is_encrypted_at_rest(): void
    {
        $raw = DB::table('payouts')->value('account_number');

        $this->assertNotSame('1234567890', $raw);
        $this->assertSame('1234567890', $this->payout->fresh()->account_number);
    }
}
