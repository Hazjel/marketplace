<?php

namespace Tests\Unit;

use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\Models\User;
use App\Repositories\BuyerBalanceRepository;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuyerBalanceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private BuyerBalanceRepository $repository;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new BuyerBalanceRepository;
        $this->buyer = Buyer::create(['user_id' => User::factory()->create()->id, 'phone_number' => '0813']);
    }

    private function balance(): string
    {
        return (string) Buyer::where('id', $this->buyer->id)->value('balance');
    }

    private function assertBalance(int $expected): void
    {
        $this->assertEquals($expected, $this->balance());
        // The cached total always equals its ledger.
        $this->assertEquals($expected, (float) BuyerBalanceHistory::where('buyer_id', $this->buyer->id)->sum('amount'));
    }

    public function test_a_new_buyer_starts_at_zero(): void
    {
        $this->assertBalance(0);
    }

    public function test_credit_adds_to_the_balance_and_records_a_positive_entry(): void
    {
        $this->assertTrue($this->repository->credit($this->buyer->id, '150000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1', $this->buyer, 'Refund pesanan'));

        $this->assertBalance(150000);
        $history = BuyerBalanceHistory::sole();
        $this->assertSame('refund', $history->type);
        $this->assertSame('150000.00', $history->amount);
        $this->assertSame($this->buyer->getMorphClass(), $history->reference_type);
        $this->assertSame($this->buyer->id, $history->reference_id);
        $this->assertSame('Refund pesanan', $history->remarks);
    }

    public function test_debit_takes_from_the_balance_and_records_a_negative_entry(): void
    {
        $this->repository->credit($this->buyer->id, '150000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');

        $this->assertTrue($this->repository->debit($this->buyer->id, '40000.00', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-2'));

        $this->assertBalance(110000);
        $this->assertSame('-40000.00', BuyerBalanceHistory::where('unique_ref', 'payment:order-2')->value('amount'));
    }

    public function test_a_repeated_unique_ref_is_applied_once(): void
    {
        $this->assertTrue($this->repository->credit($this->buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1'));
        $this->assertFalse($this->repository->credit($this->buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1'));

        $this->assertTrue($this->repository->debit($this->buyer->id, '20000', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-2'));
        $this->assertFalse($this->repository->debit($this->buyer->id, '20000', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-2'));

        $this->assertBalance(30000);
        $this->assertSame(2, BuyerBalanceHistory::count());
    }

    public static function conflictingReuses(): array
    {
        return [
            'other buyer' => ['other', '50000', BuyerBalanceHistory::TYPE_REFUND],
            'other type' => ['same', '50000', BuyerBalanceHistory::TYPE_PAYMENT_RETURNED],
            'other amount' => ['same', '60000', BuyerBalanceHistory::TYPE_REFUND],
        ];
    }

    #[DataProvider('conflictingReuses')]
    public function test_a_unique_ref_reused_for_a_different_mutation_is_a_caller_bug(string $who, string $amount, string $type): void
    {
        $this->repository->credit($this->buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');
        $buyerId = $who === 'same'
            ? $this->buyer->id
            : Buyer::create(['user_id' => User::factory()->create()->id, 'phone_number' => '0814'])->id;

        try {
            $this->repository->credit($buyerId, $amount, $type, 'refund:order-1');
            $this->fail('Reusing a unique_ref for another mutation must throw.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('refund:order-1', $e->getMessage());
        }

        $this->assertBalance(50000);
        $this->assertSame(1, BuyerBalanceHistory::count());
    }

    public function test_a_repeated_debit_matches_on_its_signed_amount(): void
    {
        $this->repository->credit($this->buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');
        $this->repository->debit($this->buyer->id, '20000', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-2');

        // Same ref and type, but a credit is not the debit it was used for.
        $this->expectException(LogicException::class);
        $this->repository->credit($this->buyer->id, '20000', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-2');
    }

    public function test_debit_down_to_exactly_zero_is_allowed(): void
    {
        $this->repository->credit($this->buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');

        $this->assertTrue($this->repository->debit($this->buyer->id, '50000', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-2'));

        $this->assertBalance(0);
    }

    public function test_debit_beyond_the_balance_is_refused_and_changes_nothing(): void
    {
        $this->repository->credit($this->buyer->id, '50000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');

        try {
            $this->repository->debit($this->buyer->id, '50001', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-2');
            $this->fail('Debit beyond the balance must throw.');
        } catch (Exception $e) {
            $this->assertSame('Saldo Blukios tidak mencukupi', $e->getMessage());
            $this->assertSame(422, $e->getCode());
        }

        $this->assertBalance(50000);
        $this->assertSame(1, BuyerBalanceHistory::count());
    }

    public static function invalidAmounts(): array
    {
        return [
            'zero' => ['0'],
            'zero with decimals' => ['0.00'],
            'negative' => ['-1000'],
            'fractional rupiah' => ['1000.50'],
            'not a number' => ['abc'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_credit_rejects_a_non_positive_or_malformed_amount(string $amount): void
    {
        $this->expectExceptionCode(422);

        $this->repository->credit($this->buyer->id, $amount, BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');
    }

    #[DataProvider('invalidAmounts')]
    public function test_debit_rejects_a_non_positive_or_malformed_amount(string $amount): void
    {
        $this->expectExceptionCode(422);

        $this->repository->debit($this->buyer->id, $amount, BuyerBalanceHistory::TYPE_PAYMENT, 'payment:order-1');
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->credit($this->buyer->id, '1000', 'topup', 'topup:1');
    }

    public function test_an_unknown_buyer_is_refused(): void
    {
        $this->expectExceptionCode(404);

        $this->repository->credit('00000000-0000-0000-0000-000000000000', '1000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');
    }

    public function test_the_buyer_row_is_read_before_the_idempotency_and_balance_checks(): void
    {
        // On Postgres that first read is SELECT ... FOR UPDATE, so two
        // concurrent mutations of one buyer run one after the other. SQLite
        // drops the lock clause, so only the ordering is checked here.
        DB::enableQueryLog();
        $this->repository->credit($this->buyer->id, '1000', BuyerBalanceHistory::TYPE_REFUND, 'refund:order-1');
        $queries = array_column(DB::getQueryLog(), 'query');

        $this->assertStringContainsString('from "buyers"', $queries[0]);
        $this->assertStringContainsString('from "buyer_balance_histories"', $queries[1]);
    }
}
