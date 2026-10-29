<?php

namespace Tests\Feature;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\PaymentGatewayInterface;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EscrowRefundIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Buyer $buyer;

    private Store $store;

    private StoreBalance $balance;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seller = User::factory()->create();
        $this->seller->assignRole('store');
        $this->store = Store::factory()->create(['user_id' => $this->seller->id]);
        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $this->buyer = Buyer::factory()->create(['user_id' => $buyer->id, 'balance' => 50000]);
        $this->balance = StoreBalance::create([
            'store_id' => $this->store->id, 'balance' => 5000, 'pending_balance' => 170000,
        ]);
        $category = ProductCategory::create(['name' => 'Audit', 'slug' => 'refund-integrity', 'description' => 'Test']);
        $this->product = Product::create([
            'store_id' => $this->store->id, 'product_category_id' => $category->id,
            'name' => 'Audit', 'slug' => 'refund-integrity', 'description' => 'Test',
            'condition' => 'new', 'price' => 100000, 'weight' => 1, 'stock' => 10,
        ]);
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldNotReceive('refund');
        $this->app->instance(PaymentGatewayInterface::class, $gateway);
    }

    private function order(string $code, int $amount): Transaction
    {
        $order = Transaction::create([
            'code' => $code, 'buyer_id' => $this->buyer->id, 'store_id' => $this->store->id,
            'address_id' => 1, 'address' => 'Synthetic QA', 'city' => 'Jakarta', 'postal_code' => '12345',
            'shipping' => 'JNE', 'shipping_type' => 'REG', 'shipping_cost' => 0, 'tax' => 0,
            'grand_total' => $amount, 'seller_amount' => $amount, 'balance_used' => 10000,
            'payment_status' => 'paid', 'delivery_status' => 'processing',
        ]);
        TransactionDetail::create([
            'transaction_id' => $order->id, 'product_id' => $this->product->id, 'qty' => 2, 'subtotal' => $amount,
        ]);
        $this->balance->storeBalanceHistories()->create([
            'type' => 'pending_income', 'reference_id' => $order->id,
            'reference_type' => Transaction::class, 'unique_ref' => 'pending_income:'.$order->id,
            'amount' => $amount, 'remarks' => 'Synthetic QA',
        ]);

        return $order;
    }

    public static function inconsistentEscrow(): array
    {
        return [
            'missing order ledger' => ['missing-ledger'],
            'insufficient wallet pending balance' => ['insufficient'],
            'missing store wallet' => ['missing-wallet'],
        ];
    }

    #[DataProvider('inconsistentEscrow')]
    public function test_cancel_rejects_inconsistent_escrow_without_any_refund_side_effects(string $state): void
    {
        $order = $this->order('QA-REFUND', 100000);
        $protected = $this->order('QA-PROTECTED', 70000);
        if ($state === 'missing-ledger') {
            StoreBalanceHistory::where('reference_id', $order->id)->delete();
            $this->balance->update(['pending_balance' => 70000]);
        } elseif ($state === 'insufficient') {
            $this->balance->update(['pending_balance' => 90000]);
        } else {
            $this->balance->delete();
        }
        $pending = $this->balance->fresh()?->pending_balance;
        $histories = StoreBalanceHistory::orderBy('id')->get()->toArray();

        $this->actingAs($this->seller)->postJson("/api/transaction/{$order->id}/cancel", ['reason' => 'Stok habis'])
            ->assertStatus(422);

        $this->assertSame($pending, $this->balance->fresh()?->pending_balance);
        $this->assertSame($histories, StoreBalanceHistory::orderBy('id')->get()->toArray());
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(50000, (int) $this->buyer->fresh()->balance);
        $this->assertSame(0, BuyerBalanceHistory::count());
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->delivery_status);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertNull($order->fresh()->refund_status);
        if ($state === 'missing-ledger') {
            app(EscrowRepositoryInterface::class)->release($protected);
            $this->assertSame(75000, (int) $this->balance->fresh()->balance);
            $this->assertSame(0, (int) $this->balance->fresh()->pending_balance);
        }
    }

    public static function storedSellerAmounts(): array
    {
        return ['changed amount' => [999999], 'legacy null amount' => [null]];
    }

    #[DataProvider('storedSellerAmounts')]
    public function test_refund_uses_its_pending_ledger_amount_and_preserves_other_orders(?int $sellerAmount): void
    {
        $order = $this->order('QA-REFUND-AMOUNT', 100000);
        $protected = $this->order('QA-PROTECTED-AMOUNT', 70000);
        $order->update(['seller_amount' => $sellerAmount, 'grand_total' => 999999]);

        app(EscrowRepositoryInterface::class)->refund($order);

        $this->assertSame(70000, (int) $this->balance->fresh()->pending_balance);
        $this->assertSame(-100000, (int) StoreBalanceHistory::where('reference_id', $order->id)->where('type', 'refunded')->value('amount'));
        $this->assertSame(5000, (int) $this->balance->fresh()->balance);
        app(EscrowRepositoryInterface::class)->release($protected);
        $this->assertSame(75000, (int) $this->balance->fresh()->balance);
        $this->assertSame(0, (int) $this->balance->fresh()->pending_balance);
    }
}
