<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
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

    private function order(string $code, array $overrides = []): Transaction
    {
        $transaction = Transaction::create($overrides + [
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

    /**
     * $first takes the transactions row lock inside its real path and holds
     * it; $second starts only then, and $first may commit only once
     * PostgreSQL shows $second's backend waiting on a lock $first holds.
     *
     * @param  array{0: string, 1: string}  $first
     * @param  array{0: string, 1: string}  $second
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function runHeldFirst(array $first, array $second): array
    {
        $hold = random_int(100000, 2000000000);
        DB::select('select pg_advisory_lock(?)', [$hold]);

        try {
            $holder = $this->startWorker($first, $hold);
            $this->assertSame('locked', $this->readLine($holder[1][1]), 'first worker never reached its row lock');
            $waiter = $this->startWorker($second, 0);
            $this->assertWaitingOnLockHeldBy($waiter[2], $holder[2]);
        } finally {
            DB::select('select pg_advisory_unlock(?)', [$hold]);
        }

        return [$this->finishWorker($holder), $this->finishWorker($waiter)];
    }

    /** @return array{0: resource, 1: array<int, resource>, 2: int} */
    private function startWorker(array $worker, int $hold): array
    {
        [$process, $pipes] = $this->openProcess(
            [PHP_BINARY, base_path('tests/Support/P0EscrowWorker.php'), $worker[0], $worker[1], '0', (string) $hold],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        );
        $line = $this->readLine($pipes[1]);
        $this->assertMatchesRegularExpression('/^pid:\d+$/', $line);

        return [$process, $pipes, (int) substr($line, 4)];
    }

    /**
     * Non-blocking fgets, not stream_select: select does not see a line PHP
     * already buffered while reading the one before it.
     *
     * @param  resource  $pipe
     */
    private function readLine($pipe, int $timeoutSeconds = 30): string
    {
        stream_set_blocking($pipe, false);
        $line = '';
        $deadline = microtime(true) + $timeoutSeconds;
        while (! str_ends_with($line, "\n") && microtime(true) < $deadline) {
            $chunk = fgets($pipe);
            if ($chunk === false) {
                usleep(10000);
            } else {
                $line .= $chunk;
            }
        }
        $this->assertStringEndsWith("\n", $line, 'worker printed no full line in time: '.$line);

        return trim($line);
    }

    // Polled with a deadline; what proves the wait is PostgreSQL's own view.
    private function assertWaitingOnLockHeldBy(int $waiter, int $holder): void
    {
        $deadline = microtime(true) + 15;
        do {
            $row = DB::selectOne(
                'select wait_event_type, wait_event, query, ? = any(pg_blocking_pids(pid)) as blocked from pg_stat_activity where pid = ?',
                [$holder, $waiter],
            );
            if ($row && $row->wait_event_type === 'Lock' && $row->blocked) {
                $this->assertContains($row->wait_event, ['transactionid', 'tuple']);
                $this->assertStringContainsString('from "transactions"', $row->query);
                $this->assertStringContainsString('for update', $row->query);

                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->fail('second worker never waited on the first one\'s row lock: '.json_encode($row));
    }

    /** @return array<string, mixed> */
    private function finishWorker(array $worker): array
    {
        [$process, $pipes] = $worker;
        stream_set_blocking($pipes[1], true);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $lines = preg_split('/\R/', trim($stdout));
        $decoded = json_decode((string) end($lines), true);
        $this->assertIsArray($decoded, $stderr ?: $stdout);

        return $decoded;
    }

    /** Saldo-paid processing order with 2 units of a 10-stock product. */
    private function unshippedOrderWithStock(string $code): array
    {
        $category = ProductCategory::create(['name' => 'Umum', 'slug' => 'umum-'.$code, 'description' => 'x']);
        $product = Product::factory()->create([
            'store_id' => $this->balance->store_id,
            'product_category_id' => $category->id,
            'stock' => 10,
        ]);
        $transaction = $this->order($code, ['delivery_status' => 'processing', 'balance_used' => 100000]);
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'qty' => 2,
            'subtotal' => 90000,
        ]);

        return [$transaction, $product];
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

    // transaction:cancel-unshipped (cancelPaidOrder) racing a seller who enters the resi.
    public function test_parallel_ship_and_auto_cancel_have_one_winner_and_no_refund_for_a_shipped_order(): void
    {
        // Paid fully with Saldo: a winning cancel refunds right here, no Midtrans call.
        $transaction = $this->order('P0_CONCURRENT_SHIP_CANCEL', ['delivery_status' => 'processing', 'balance_used' => 100000]);
        $results = $this->runParallel([
            ['ship', $transaction->id],
            ['cancel', $transaction->id],
        ]);

        $this->assertCount(1, array_filter($results, fn (array $result) => $result['ok']));
        $transaction->refresh();
        $refunded = StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_REFUNDED)->count();
        if ($transaction->delivery_status === 'delivering') {
            $this->assertSame(0, $refunded);
            $this->assertNull($transaction->refund_status);
            $this->assertEquals(0, (float) $this->buyer->fresh()->balance);
        } else {
            $this->assertSame('cancelled', $transaction->delivery_status);
            $this->assertSame(1, $refunded);
            $this->assertEquals(100000, (float) $this->buyer->fresh()->balance);
        }
        $this->assertEscrowInvariant();
    }

    public function test_a_seller_holding_the_lock_ships_and_the_waiting_auto_cancel_is_rejected(): void
    {
        [$transaction, $product] = $this->unshippedOrderWithStock('P0_SHIP_FIRST');
        $saldo = (float) $this->buyer->fresh()->balance;

        [$ship, $cancel] = $this->runHeldFirst(['ship', $transaction->id], ['cancel', $transaction->id]);

        $this->assertTrue($ship['ok'], json_encode($ship));
        $this->assertSame(['ok' => false, 'code' => 422, 'exception' => \Exception::class, 'message' => 'Pesanan yang sudah dikirim tidak bisa dibatalkan'], $cancel);
        $transaction->refresh();
        $this->assertSame('delivering', $transaction->delivery_status);
        $this->assertSame('JNE-RACE', $transaction->tracking_number);
        $this->assertSame('paid', $transaction->payment_status);
        $this->assertNull($transaction->refund_status);
        $this->assertSame(0, StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_REFUNDED)->count());
        $this->assertSame(0, BuyerBalanceHistory::where('buyer_id', $this->buyer->id)->count());
        $this->assertEquals($saldo, (float) $this->buyer->fresh()->balance);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertEscrowInvariant();
        $this->assertEquals(100000, (float) $this->balance->fresh()->pending_balance);
    }

    public function test_an_auto_cancel_holding_the_lock_wins_and_the_waiting_seller_is_told_it_was_cancelled(): void
    {
        [$transaction, $product] = $this->unshippedOrderWithStock('P0_CANCEL_FIRST');
        $saldo = (float) $this->buyer->fresh()->balance;

        [$cancel, $ship] = $this->runHeldFirst(['cancel', $transaction->id], ['ship', $transaction->id]);

        $this->assertTrue($cancel['ok'], json_encode($cancel));
        $this->assertSame(['ok' => false, 'code' => 422, 'exception' => \Exception::class, 'message' => 'Pesanan sudah dibatalkan'], $ship);
        $transaction->refresh();
        $this->assertSame('cancelled', $transaction->delivery_status);
        $this->assertNull($transaction->tracking_number);
        $this->assertSame('refunded', $transaction->refund_status);
        $this->assertSame(1, StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_REFUNDED)->count());
        $this->assertSame(1, BuyerBalanceHistory::where('buyer_id', $this->buyer->id)->where('type', BuyerBalanceHistory::TYPE_REFUND)->count());
        $this->assertEquals($saldo + 100000, (float) $this->buyer->fresh()->balance);
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertNotNull($transaction->stock_restored_at);
        $this->assertEscrowInvariant();
        $this->assertEquals(0, (float) $this->balance->fresh()->pending_balance);
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
