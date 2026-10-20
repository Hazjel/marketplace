<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Buyer;
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
class CancelUnpaidConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private Buyer $buyer;

    private User $buyerUser;

    private Product $product;

    private StoreBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Cancellation and settlement races require PostgreSQL.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->app->instance(PaymentGatewayInterface::class, new class implements PaymentGatewayInterface
        {
            public function getSnapToken(Transaction $transaction): ?string
            {
                return $transaction->snap_token;
            }

            public function refund(Transaction $transaction, string $reason): string
            {
                return self::REFUND_DONE;
            }

            public function expirePayment(string $orderId): void {}
        });

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id]);
        $this->balance = StoreBalance::create([
            'store_id' => $store->id,
            'balance' => 0,
            'pending_balance' => 0,
        ]);

        $category = ProductCategory::create([
            'name' => 'F1 Race Category',
            'slug' => 'f1-race-category',
            'description' => 'race',
        ]);
        $this->product = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'stock' => 2,
            'price' => 100000,
        ]);

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->assignRole('buyer');
        $this->buyer = Buyer::factory()->create(['user_id' => $this->buyerUser->id]);
    }

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

    public function test_cancel_and_settlement_race_has_one_winner_and_preserves_inventory_escrow_and_late_refund(): void
    {
        $transaction = $this->order();
        $token = User::findOrFail($this->buyer->user_id)->createToken('f1-race')->plainTextToken;
        $amount = number_format((float) $transaction->grand_total, 2, '.', '');
        $barrier = random_int(100000, 2000000000);

        $this->commitFixtureBeforeWorkers();
        DB::select('select pg_advisory_lock(?)', [$barrier]);
        $processes = [
            $this->openWorker('CancelUnpaidWorker.php', [(string) $transaction->id, $token, (string) $barrier]),
            $this->openWorker('SettlementWebhookWorker.php', [$transaction->paymentCode(), $amount, (string) $barrier]),
        ];
        DB::select('select pg_advisory_unlock(?)', [$barrier]);

        $statuses = [];
        foreach ($processes as [$process, $pipes]) {
            $stdout = trim(stream_get_contents($pipes[1]));
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $this->assertSame('', $stderr, $stderr);
            $this->assertMatchesRegularExpression('/^\d+$/', $stdout);
            $statuses[] = (int) $stdout;
        }

        $transaction->refresh();
        $this->assertContains($transaction->payment_status, ['failed', 'paid']);
        $expectedStock = $transaction->payment_status === 'failed' ? 2 : 1;
        $this->assertSame($expectedStock, $this->product->fresh()->stock, 'race must not duplicate stock movement');

        $histories = StoreBalanceHistory::where('store_balance_id', $this->balance->id)->get();
        $this->assertSame($histories->unique('unique_ref')->count(), $histories->count());
        $this->assertSame(
            (float) $histories->where('type', StoreBalanceHistory::TYPE_PENDING_INCOME)->sum('amount')
            - (float) $histories->where('type', StoreBalanceHistory::TYPE_RELEASED)->sum('amount')
            - (float) $histories->where('type', StoreBalanceHistory::TYPE_REFUNDED)->sum('amount'),
            (float) $this->balance->fresh()->pending_balance,
        );

        if ($transaction->payment_status === 'failed') {
            $this->assertSame(200, $statuses[0], 'cancel must win when the order is failed');
            $this->assertSame('cancelled', $transaction->delivery_status);
            $this->assertNotNull($transaction->refresh()->refund_status, 'late settlement must enter refund flow');
            $this->assertNotSame('paid', $transaction->payment_status);
        } else {
            $this->assertSame(422, $statuses[0], 'cancel must lose when settlement wins');
            $this->assertSame('paid', $transaction->payment_status);
            $this->assertSame(0, StoreBalanceHistory::where('reference_id', $transaction->id)
                ->where('type', StoreBalanceHistory::TYPE_REFUNDED)->count());
        }
    }

    public function test_settlement_after_cancel_starts_late_refund_without_repeating_inventory_or_ledger_changes(): void
    {
        $transaction = $this->order();
        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson('/api/transaction/'.$transaction->id.'/cancel-unpaid')
            ->assertOk();

        $stockAfterCancel = $this->product->fresh()->stock;
        $amount = number_format((float) $transaction->grand_total, 2, '.', '');
        $signature = hash('sha512', $transaction->paymentCode().'200'.$amount.config('midtrans.serverKey'));
        $this->postJson('/api/midtrans-callback', [
            'order_id' => $transaction->paymentCode(),
            'status_code' => '200',
            'gross_amount' => $amount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('failed', $transaction->payment_status);
        $this->assertSame('cancelled', $transaction->delivery_status);
        $this->assertNotNull($transaction->refund_status);
        $this->assertSame($stockAfterCancel, $this->product->fresh()->stock);
        $histories = StoreBalanceHistory::where('store_balance_id', $this->balance->id)->get();
        $this->assertSame($histories->unique('unique_ref')->count(), $histories->count());
    }

    private function order(): Transaction
    {
        $transaction = Transaction::create([
            'code' => 'F1_RACE_CANCEL_SETTLE',
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->balance->store_id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'tax' => 0,
            'grand_total' => 115000,
            'payment_status' => 'unpaid',
            'delivery_status' => 'pending',
        ]);
        $transaction->forceFill([
            'payment_code' => $transaction->code,
            'snap_token' => 'test-snap-token',
        ])->save();
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'subtotal' => 100000,
        ]);
        DB::table('products')->where('id', $this->product->id)->decrement('stock');

        return $transaction->fresh();
    }

    private function commitFixtureBeforeWorkers(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }
    }

    /** @param list<string> $arguments */
    private function openWorker(string $worker, array $arguments): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            array_merge([PHP_BINARY, base_path('tests/Support/'.$worker)], $arguments),
            $descriptor,
            $pipes,
            base_path(),
        );
        $this->assertIsResource($process);

        return [$process, $pipes];
    }
}
