<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
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
use Illuminate\Support\Facades\Log;
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
        return $this->unpaidOrderFor($this->store, $this->product, $code, $paymentCode);
    }

    private function unpaidOrderFor(Store $store, Product $product, string $code, ?string $paymentCode = null): Transaction
    {
        $transaction = Transaction::create([
            'code' => $code,
            'buyer_id' => $this->buyer->id,
            'store_id' => $store->id,
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
            'product_id' => $product->id,
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
            ->assertJsonPath('message', 'Pesanan dibatalkan.')
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
        app('auth')->forgetGuards();
        $this->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertStatus(401);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk();
        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertSame('cancelled', $transaction->fresh()->delivery_status);
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
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $secondStore = Store::factory()->create(['user_id' => $seller->id]);
        StoreBalance::create(['store_id' => $secondStore->id, 'balance' => 0, 'pending_balance' => 0]);
        $secondProduct = Product::factory()->create([
            'store_id' => $secondStore->id,
            'product_category_id' => ProductCategory::query()->firstOrFail()->id,
            'stock' => 4,
            'price' => 100000,
        ]);
        $second = $this->unpaidOrderFor($secondStore, $secondProduct, 'F1_SHARED_B', $paymentCode);
        $firstStock = $this->product->fresh()->stock;
        $secondStock = $secondProduct->fresh()->stock;

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$first->id}/cancel-unpaid")
            ->assertOk()
            ->assertJsonFragment(['code' => $first->code])
            ->assertJsonFragment(['code' => $second->code]);

        $this->assertSame(['failed', 'failed'], Transaction::whereIn('id', [$first->id, $second->id])
            ->orderBy('code')->pluck('payment_status')->all());
        $this->assertSame($firstStock + 1, $this->product->fresh()->stock);
        $this->assertSame($secondStock + 1, $secondProduct->fresh()->stock);
    }

    public function test_stock_and_balance_are_restored_once_on_repeated_cancel(): void
    {
        $transaction = $this->unpaidOrder();
        $transaction->forceFill(['balance_used' => 25000])->save();
        $this->buyer->forceFill(['balance' => 0])->save();
        BuyerBalanceHistory::create([
            'buyer_id' => $this->buyer->id,
            'type' => BuyerBalanceHistory::TYPE_PAYMENT,
            'amount' => -25000,
            'reference_type' => Transaction::class,
            'reference_id' => $transaction->id,
            'unique_ref' => 'payment:'.$transaction->id,
            'remarks' => 'F1 fixture',
        ]);
        DB::table('products')->where('id', $this->product->id)->decrement('stock');
        $before = $this->product->fresh()->stock;

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk()
            ->assertJsonPath('message', 'Pesanan dibatalkan. Saldo Blukios yang digunakan sudah dikembalikan.');
        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertStatus(422);

        $this->assertSame($before + 1, $this->product->fresh()->stock);
        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertSame(1, count($this->expiredPayments));
        $this->assertSame(1, BuyerBalanceHistory::where('type', BuyerBalanceHistory::TYPE_PAYMENT_RETURNED)->count());
        $this->assertSame(25000, (int) $this->buyer->fresh()->balance);
    }

    public function test_gateway_failure_does_not_undo_database_cancellation_and_late_settlement_refunds(): void
    {
        $transaction = $this->unpaidOrder('F1_LATE_SETTLEMENT');
        $this->failExpire = true;
        Log::spy();

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/cancel-unpaid")
            ->assertOk();

        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertNotSame('paid', $transaction->fresh()->payment_status);
        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('warning');
        $amount = number_format((float) $transaction->grand_total, 2, '.', '');
        $this->postJson('/api/midtrans-callback', [
            'order_id' => $transaction->paymentCode(),
            'status_code' => '200',
            'gross_amount' => $amount,
            'signature_key' => hash('sha512', $transaction->paymentCode().'200'.$amount.config('midtrans.serverKey')),
            'transaction_status' => 'settlement',
        ])->assertOk();
        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertSame(0, BuyerBalanceHistory::where('type', BuyerBalanceHistory::TYPE_PAYMENT_RETURNED)->count());
    }
}
