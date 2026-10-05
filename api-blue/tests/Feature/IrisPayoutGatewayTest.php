<?php

namespace Tests\Feature;

use App\Services\IrisPayoutGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class IrisPayoutGatewayTest extends TestCase
{
    private const BASE = 'https://iris.test/iris';

    private IrisPayoutGateway $iris;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.irisKey' => 'iris-key', 'midtrans.irisBaseUrl' => self::BASE]);
        $this->iris = new IrisPayoutGateway;
    }

    public function test_is_configured_follows_the_key(): void
    {
        $this->assertTrue($this->iris->isConfigured());

        config(['midtrans.irisKey' => '']);
        $this->assertFalse($this->iris->isConfigured());
    }

    public function test_banks_are_cached(): void
    {
        Http::fake([self::BASE.'/api/v1/beneficiary_banks' => Http::response(['beneficiary_banks' => [
            ['code' => 'bca', 'name' => 'Bank Central Asia'],
        ]])]);

        $this->assertSame([['code' => 'bca', 'name' => 'Bank Central Asia']], $this->iris->banks());
        $this->assertSame([['code' => 'bca', 'name' => 'Bank Central Asia']], $this->iris->banks());
        Http::assertSentCount(1);
    }

    public function test_validate_account_returns_the_holder_name(): void
    {
        Http::fake([self::BASE.'/api/v1/account_validation*' => Http::response([
            'account_name' => 'Budi Santoso', 'account_no' => '1234567890', 'bank_name' => 'bca', 'id' => 'x',
        ])]);

        $this->assertSame('Budi Santoso', $this->iris->validateAccount('bca', '1234567890'));
        Http::assertSent(fn (Request $r) => $r['bank'] === 'bca' && $r['account'] === '1234567890');
    }

    public function test_validate_account_returns_null_for_an_invalid_account(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error_message' => 'Account does not exist'], 400)
            ->push([], 404)
            ->push([], 422)]);

        foreach ([400, 404, 422] as $status) {
            $this->assertNull($this->iris->validateAccount('bca', '000'), "HTTP {$status}");
        }
    }

    public function test_validate_account_throws_when_iris_is_down_the_key_is_wrong_or_rate_limited(): void
    {
        $statuses = [500, 401, 403, 429];
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push([], $status);
        }
        Http::fake(['*' => $sequence]);

        foreach ($statuses as $status) {
            try {
                $this->iris->validateAccount('bca', '1234567890');
                $this->fail("HTTP {$status} must throw");
            } catch (RuntimeException $e) {
                $this->assertSame($status, $e->getCode());
            }
        }
    }

    public function test_create_payout_returns_the_reference_and_sends_basic_auth(): void
    {
        Http::fake([self::BASE.'/api/v1/payouts' => Http::response(['payouts' => [
            ['status' => 'queued', 'reference_no' => 'ref-123'],
        ]], 201)]);

        $ref = $this->iris->createPayout('Budi Santoso', '1234567890', 'bca', '18100.00', 'Refund TRX-001 #pesanan!', 'payout-uuid-1');

        $this->assertSame('ref-123', $ref);
        Http::assertSent(function (Request $r) {
            $payout = $r['payouts'][0];

            return $r->method() === 'POST'
                && $r->hasHeader('Authorization', 'Basic '.base64_encode('iris-key:'))
                && $r->hasHeader('X-Idempotency-Key', 'payout-uuid-1')
                && $payout['amount'] === '18100.00'
                && $payout['beneficiary_account'] === '1234567890'
                && $payout['beneficiary_bank'] === 'bca'
                && $payout['notes'] === 'Refund TRX 001 pesanan';
        });
    }

    public function test_errors_carry_the_http_status_or_zero_for_network_failures(): void
    {
        Http::fake(['*' => Http::response(['error_message' => 'Invalid amount'], 422)]);
        try {
            $this->iris->createPayout('Budi', '1234567890', 'bca', '1.00', 'x', 'payout-uuid-2');
            $this->fail('422 must throw');
        } catch (RuntimeException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('Invalid amount', $e->getMessage());
        }

        Http::fake(['*' => fn () => throw new ConnectionException('timeout for '.self::BASE.'?account=1234567890')]);
        try {
            $this->iris->validateAccount('bca', '1234567890');
            $this->fail('connection error must throw');
        } catch (RuntimeException $e) {
            $this->assertSame(0, $e->getCode());
            $this->assertStringNotContainsString('1234567890', $e->getMessage());
        }
    }

    public function test_malformed_banks_and_balance_bodies_throw_retryable_502(): void
    {
        Http::fake([
            self::BASE.'/api/v1/beneficiary_banks' => Http::sequence()
                ->push(['unexpected' => []])
                ->push(['beneficiary_banks' => [['code' => 'bca']]])
                ->push(['beneficiary_banks' => [['code' => 'bca', 'name' => 'Bank Central Asia']]]),
            self::BASE.'/api/v1/balance' => Http::response(['balance' => null]),
        ]);

        foreach (['banks', 'banks', 'balance'] as $method) {
            try {
                $this->iris->{$method}();
                $this->fail("{$method}() must throw on a malformed body");
            } catch (RuntimeException $e) {
                $this->assertSame(502, $e->getCode());
            }
        }

        // The failures were not cached: the next good answer is used.
        $this->assertSame([['code' => 'bca', 'name' => 'Bank Central Asia']], $this->iris->banks());
    }
}
