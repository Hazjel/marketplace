<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Buyer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CancelUnpaidTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $buyerUser;

    private Buyer $buyer;

    private User $sellerUser;

    private Store $store;

    private Product $product;

    /** @var list<string> */
    public array $expiredPayments = [];

    public bool $failExpire = false;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->expiredPayments = [];
        $this->app->instance(PaymentGatewayInterface::class, new class($this) implements PaymentGatewayInterface
        {
            public function __construct(private CancelUnpaidTransactionTest $test) {}

            public function getSnapToken(Transaction $transaction): ?string
            {
                return $transaction->snap_token;
            }

            public function refund(Transaction $transaction, string $reason): string
            {
                return self::REFUND_DONE;
            }

            public function expirePayment(string $orderId): void
            {
                if ($this->test->failExpire) {
                    throw new \RuntimeException('Midtrans unavailable');
                }

                $this->test->expiredPayments[] = $orderId;
            }
        });

        $this->sellerUser = User::factory()->create();
        $this->sellerUser->assignRole('store');
        $this->store = Store::factory()->create(['user_id' => $this->sellerUser->id]);
        StoreBalance::create(['store_id' => $this->store->id, 'balance' => 0, 'pending_balance' => 0]);

        $category = ProductCategory::create([
            'name' => 'F1 Category',
            'slug' => 'f1-category',
            'description' => 'F1',
        ]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'stock' => 4,
            'price' => 100000,
        ]);

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->assignRole('buyer');
        $this->buyer = Buyer::factory()->create(['user_id' => $this->buyerUser->id]);
    }

    private function unpaidOrder(string $code = 'F1_UNPAID', ?string $paymentCode = null): Transaction
    {
        $transaction = Transaction::create([
            'code' => $code,
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'tax' => 0,
            'grand_total' => 116000,
            'payment_status' => 'unpaid',
            'delivery_status' => 'pending',
        ]);
        $transaction->forceFill([
            'payment_code' => $paymentCode ?? $code,
            'snap_token' => 'test-snap-token',
        ])->save();
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return $transaction->fresh();
    }

    public function test_owner_buyer_can_cancel_unpaid_and_expire_payment(): void
    {
        $transaction = $this->unpaidOrder();

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'failed');

        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertSame([$transaction->paymentCode()], $this->expiredPayments);
    }

    public function test_seller_other_buyer_and_guest_cannot_cancel_unpaid(): void
    {
        $transaction = $this->unpaidOrder();
        $otherBuyer = User::factory()->create();
        $otherBuyer->assignRole('buyer');
        $seller = $this->sellerUser;

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertStatus(403);
        $this->actingAs($otherBuyer, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertStatus(403);
        $this->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertStatus(401);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk();
        $this->assertSame('failed', $transaction->fresh()->payment_status);
    }

    public function test_paid_order_is_rejected_and_failed_order_is_idempotent(): void
    {
        $paid = $this->unpaidOrder('F1_PAID');
        $paid->update(['payment_status' => 'paid']);
        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$paid->id}/cancel-unpaid")
            ->assertStatus(422);

        $failed = $this->unpaidOrder('F1_FAILED');
        $failed->update(['payment_status' => 'failed']);
        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$failed->id}/cancel-unpaid")
            ->assertStatus(422);
        $this->assertSame([], $this->expiredPayments);
    }

    public function test_shared_payment_cancels_every_order_and_lists_them_in_response(): void
    {
        $paymentCode = 'F1_SHARED_PAYMENT';
        $first = $this->unpaidOrder('F1_SHARED_A', $paymentCode);
        $second = $this->unpaidOrder('F1_SHARED_B', $paymentCode);

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$first->id}/cancel-unpaid")
            ->assertOk()
            ->assertJsonFragment(['code' => $first->code])
            ->assertJsonFragment(['code' => $second->code]);

        $this->assertSame(['failed', 'failed'], Transaction::whereIn('id', [$first->id, $second->id])
            ->orderBy('code')->pluck('payment_status')->all());
    }

    public function test_stock_and_balance_are_restored_once_on_repeated_cancel(): void
    {
        $transaction = $this->unpaidOrder();
        DB::table('products')->where('id', $this->product->id)->decrement('stock');
        $before = $this->product->fresh()->stock;

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk();
        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk();

        $this->assertSame($before + 1, $this->product->fresh()->stock);
        $this->assertSame('failed', $transaction->fresh()->payment_status);
    }

    public function test_gateway_failure_does_not_undo_database_cancellation_and_late_settlement_refunds(): void
    {
        $transaction = $this->unpaidOrder('F1_LATE_SETTLEMENT');
        $this->failExpire = true;

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk();

        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertNotSame('paid', $transaction->fresh()->payment_status);
    }

    #[Group('pgsql-concurrency')]
    public function test_cancel_unpaid_race_with_settlement_has_one_winner(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Cancellation race requires PostgreSQL row locks.');
        }

        $transaction = $this->unpaidOrder('F1_RACE');
        $token = $this->buyerUser->createToken('f1-race')->plainTextToken;
        $barrier = random_int(100000, 2000000000);
        DB::select('select pg_advisory_lock(?)', [$barrier]);
        $processes = [];
        foreach ([1, 2] as $_) {
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open([
                PHP_BINARY,
                base_path('tests/Support/CancelUnpaidWorker.php'),
                (string) $transaction->id,
                $token,
                (string) $barrier,
            ], $descriptor, $pipes, base_path());
            $this->assertIsResource($process);
            $processes[] = [$process, $pipes];
        }
        DB::select('select pg_advisory_unlock(?)', [$barrier]);

        $statuses = [];
        foreach ($processes as [$process, $pipes]) {
            $statuses[] = (int) trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        sort($statuses);
        $this->assertSame([200, 422], $statuses);
        $this->assertContains($transaction->fresh()->payment_status, ['failed', 'paid']);
    }
}
