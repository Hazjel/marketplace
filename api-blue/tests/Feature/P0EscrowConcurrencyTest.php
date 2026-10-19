<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('pgsql-concurrency')]
class P0EscrowConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private StoreBalance $balance;

    private Buyer $buyer;

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $tables = DB::select(
                "select tablename from pg_tables where schemaname = 'public' and tablename <> 'migrations'",
            );
            foreach ($tables as $table) {
                $name = str_replace('"', '""', $table->tablename);
                DB::statement('truncate table "'.$name.'" restart identity cascade');
            }
        }

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency regressions require PostgreSQL row locks.');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id]);
        $this->balance = StoreBalance::create([
            'store_id' => $store->id,
            'balance' => 0,
            'pending_balance' => 0,
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $this->buyer = Buyer::factory()->create(['user_id' => $buyerUser->id]);
    }

    private function order(string $code): Transaction
    {
        $transaction = Transaction::create([
            'code' => $code,
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->balance->store_id,
            'address_id' => 1,
            'address' => 'A',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 0,
            'grand_total' => 100000,
            'payment_status' => 'paid',
            'delivery_status' => 'delivering',
            'seller_amount' => 100000,
        ]);
        StoreBalanceHistory::create([
            'store_balance_id' => $this->balance->id,
            'type' => StoreBalanceHistory::TYPE_PENDING_INCOME,
            'reference_id' => $transaction->id,
            'reference_type' => Transaction::class,
            'unique_ref' => 'pending_income:'.$transaction->id,
            'amount' => 100000,
            'remarks' => 'concurrency test',
        ]);
        $this->balance->increment('pending_balance', 100000);

        return $transaction;
    }

    /** @return list<array<string, mixed>> */
    private function runParallel(array $workers): array
    {
        $barrier = random_int(100000, 2000000000);
        DB::select('select pg_advisory_lock(?)', [$barrier]);
        $processes = [];

        foreach ($workers as [$action, $transactionId]) {
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $command = [PHP_BINARY, base_path('tests/Support/P0EscrowWorker.php'), $action, $transactionId, (string) $barrier];
            $processes[] = $this->openProcess($command, $descriptor);
        }

        DB::select('select pg_advisory_unlock(?)', [$barrier]);
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $decoded = json_decode(trim($stdout), true);
            $this->assertIsArray($decoded, $stderr ?: $stdout);
            $results[] = $decoded;
        }

        return $results;
    }

    private function openProcess(array $command, array $descriptor)
    {
        $process = proc_open($command, $descriptor, $pipes, base_path());
        $this->assertIsResource($process);

        return [$process, $pipes];
    }

    private function assertEscrowInvariant(): void
    {
        $pending = (float) StoreBalanceHistory::where('store_balance_id', $this->balance->id)
            ->where('reference_type', Transaction::class)->where('type', StoreBalanceHistory::TYPE_PENDING_INCOME)->sum('amount');
        $released = (float) StoreBalanceHistory::where('store_balance_id', $this->balance->id)
            ->where('reference_type', Transaction::class)->where('type', StoreBalanceHistory::TYPE_RELEASED)->sum('amount');
        $refunded = abs((float) StoreBalanceHistory::where('store_balance_id', $this->balance->id)
            ->where('reference_type', Transaction::class)->where('type', StoreBalanceHistory::TYPE_REFUNDED)->sum('amount'));

        $this->assertEqualsWithDelta($pending - $released - $refunded, (float) $this->balance->fresh()->pending_balance, 0.01);
        $this->assertGreaterThanOrEqual(0, (float) $this->balance->fresh()->pending_balance);
    }

    public function test_parallel_complete_complete_has_one_winner_and_one_release(): void
    {
        $transaction = $this->order('P0_CONCURRENT_COMPLETE');
        $results = $this->runParallel([
            ['complete', $transaction->id],
            ['complete', $transaction->id],
        ]);

        $this->assertCount(1, array_filter($results, fn (array $result) => $result['ok']));
        $this->assertSame(1, StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_RELEASED)->count());
        $this->assertSame(0, StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_REFUNDED)->count());
        $this->assertEscrowInvariant();
    }

    public function test_parallel_complete_cancel_has_one_winner_and_one_ledger_entry(): void
    {
        $transaction = $this->order('P0_CONCURRENT_COMPLETE_CANCEL');
        $results = $this->runParallel([
            ['complete', $transaction->id],
            ['cancel', $transaction->id],
        ]);

        $this->assertCount(1, array_filter($results, fn (array $result) => $result['ok']));
        $released = StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_RELEASED)->count();
        $refunded = StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_REFUNDED)->count();
        $this->assertSame(1, $released + $refunded);
        $this->assertEscrowInvariant();
    }

    public function test_parallel_releases_for_two_orders_keep_the_store_invariant(): void
    {
        $first = $this->order('P0_CONCURRENT_RELEASE_A');
        $second = $this->order('P0_CONCURRENT_RELEASE_B');
        $results = $this->runParallel([
            ['release', $first->id],
            ['release', $second->id],
        ]);

        $this->assertCount(2, array_filter($results, fn (array $result) => $result['ok']));
        $this->assertSame(2, StoreBalanceHistory::where('type', StoreBalanceHistory::TYPE_RELEASED)->count());
        $this->assertSame(0.0, (float) $this->balance->fresh()->pending_balance);
        $this->assertSame(200000.0, (float) $this->balance->fresh()->balance);
        $this->assertEscrowInvariant();
    }
}
