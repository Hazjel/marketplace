<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Jobs\RefundCancelledTransactionJob;
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
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SellerCancelRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $sellerUser;

    private User $buyerUser;

    private Store $store;

    private StoreBalance $storeBalance;

    private Buyer $buyer;

    private Product $product;

    /** @var array<int, string> order codes the fake gateway was asked to refund */
    private array $refundCalls = [];

    private string $gatewayResult = PaymentGatewayInterface::REFUND_DONE;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->app->instance(PaymentGatewayInterface::class, new class($this) implements PaymentGatewayInterface
        {
            public function __construct(private SellerCancelRefundTest $test) {}

            public function getSnapToken(Transaction $transaction): ?string
            {
                return null;
            }

            public function refund(Transaction $transaction, string $reason): string
            {
                return $this->test->recordRefund($transaction);
            }
        });

        $this->sellerUser = User::factory()->create();
        $this->sellerUser->assignRole('store');

        $this->store = Store::create([
            'user_id' => $this->sellerUser->id,
            'name' => 'Refund Test Store',
            'username' => 'refundstore',
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);

        $this->storeBalance = StoreBalance::create([
            'store_id' => $this->store->id,
            'balance' => 0,
            'pending_balance' => 0,
        ]);

        $category = ProductCategory::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronics',
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'name' => 'Test Gadget',
            'slug' => 'test-gadget',
            'description' => 'A test gadget',
            'price' => 100000,
            'stock' => 48,
            'weight' => 0.5,
            'condition' => 'new',
        ]);

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->assignRole('buyer');

        $this->buyer = Buyer::create([
            'user_id' => $this->buyerUser->id,
            'phone_number' => '08987654321',
        ]);
    }

    public function recordRefund(Transaction $transaction): string
    {
        $this->refundCalls[] = $transaction->code;

        if ($this->gatewayResult === 'throw') {
            throw new RuntimeException('Midtrans unreachable');
        }

        return $this->gatewayResult;
    }

    // Order paid through the real webhook path, so escrow holds the seller's share.
    private function paidOrder(string $code, string $deliveryStatus = 'pending'): Transaction
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
            'service_fee' => 1000,
            'grand_total' => 216000,
            'payment_status' => 'unpaid',
            'delivery_status' => $deliveryStatus,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'subtotal' => 200000,
        ]);

        $this->webhook($code, 'settlement')->assertOk();

        return $transaction->fresh();
    }

    private function webhook(string $code, string $status, string $paymentType = 'bank_transfer')
    {
        $signature = hash('sha512', $code.'200'.'216000.00'.config('midtrans.serverKey'));

        return $this->postJson('/api/midtrans-callback', [
            'order_id' => $code,
            'status_code' => '200',
            'gross_amount' => '216000.00',
            'signature_key' => $signature,
            'transaction_status' => $status,
            'payment_type' => $paymentType,
        ]);
    }

    private function cancelAs(User $user, Transaction $transaction)
    {
        return $this->actingAs($user)->postJson("/api/transaction/{$transaction->id}/cancel", [
            'reason' => 'Stok habis di gudang',
        ]);
    }

    public function test_seller_cancel_refunds_buyer_automatically_and_unwinds_escrow(): void
    {
        $transaction = $this->paidOrder('BLK_REFUND_001');
        $this->assertGreaterThan(0, (float) $this->storeBalance->fresh()->pending_balance);

        $this->cancelAs($this->sellerUser, $transaction)
            ->assertOk()
            ->assertJsonPath('data.delivery_status', 'cancelled')
            ->assertJsonPath('data.refund_amount', 216000);

        $transaction->refresh();
        $this->assertSame(['BLK_REFUND_001'], $this->refundCalls);
        $this->assertSame('refunded', $transaction->refund_status);
        $this->assertSame('midtrans', $transaction->refund_method);
        $this->assertNotNull($transaction->refunded_at);
        $this->assertSame('failed', $transaction->payment_status);
        $this->assertSame('Stok habis di gudang', $transaction->refund_reason);

        $this->assertEquals(0, (float) $this->storeBalance->fresh()->pending_balance);
        $this->assertEquals(0, (float) $this->storeBalance->fresh()->balance);
        $this->assertEquals(50, $this->product->fresh()->stock);
    }

    public function test_virtual_account_payment_falls_back_to_manual_transfer(): void
    {
        $this->gatewayResult = PaymentGatewayInterface::REFUND_MANUAL;
        $transaction = $this->paidOrder('BLK_REFUND_002');

        $this->cancelAs($this->sellerUser, $transaction)->assertOk();
        $this->assertSame('manual_required', $transaction->fresh()->refund_status);

        // Admin cannot close it before the buyer says where the money goes.
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)
            ->postJson("/api/transaction/{$transaction->id}/mark-refunded", ['note' => 'BCA 123'])
            ->assertStatus(422);

        $this->actingAs($this->buyerUser)
            ->postJson("/api/transaction/{$transaction->id}/refund-account", [
                'refund_bank_name' => 'BCA',
                'refund_account_number' => '1234567890',
                'refund_account_name' => 'Pembeli Uji',
            ])
            ->assertOk()
            ->assertJsonPath('data.refund_account.account_number', '1234567890');

        // Stored encrypted, not as the plain number.
        $raw = DB::table('transactions')->where('id', $transaction->id)->value('refund_account_number');
        $this->assertNotSame('1234567890', $raw);

        // The seller sees the refund state but not the buyer's bank account.
        $this->actingAs($this->sellerUser)
            ->getJson("/api/transaction/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'manual_required')
            ->assertJsonPath('data.refund_account', null);

        $this->actingAs($admin)
            ->getJson('/api/transaction/all/paginated?row_per_page=10&refund_status=manual_required')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $transaction->id)
            ->assertJsonPath('data.data.0.refund_account.bank_name', 'BCA');

        $this->actingAs($admin)
            ->postJson("/api/transaction/{$transaction->id}/mark-refunded", ['note' => 'Transfer BCA ref 778'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'refunded');

        $this->assertSame('manual', $transaction->fresh()->refund_method);
    }

    public function test_cancel_succeeds_even_when_the_gateway_is_down(): void
    {
        $this->gatewayResult = 'throw';
        $transaction = $this->paidOrder('BLK_REFUND_009');

        $this->cancelAs($this->sellerUser, $transaction)
            ->assertOk()
            ->assertJsonPath('data.delivery_status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'manual_required');

        $this->assertStringContainsString('Midtrans unreachable', $transaction->fresh()->refund_note);
        $this->assertEquals(0, (float) $this->storeBalance->fresh()->pending_balance);
    }

    public function test_failed_automatic_refund_becomes_manual_with_the_reason(): void
    {
        $transaction = $this->paidOrder('BLK_REFUND_003');
        $transaction->update(['refund_status' => 'processing']);

        (new RefundCancelledTransactionJob($transaction->id))->failed(new RuntimeException('Midtrans 412'));

        $transaction->refresh();
        $this->assertSame('manual_required', $transaction->refund_status);
        $this->assertStringContainsString('Midtrans 412', $transaction->refund_note);
    }

    public function test_only_paid_orders_not_yet_shipped_can_be_cancelled(): void
    {
        $delivering = $this->paidOrder('BLK_REFUND_004', 'delivering');
        $this->cancelAs($this->sellerUser, $delivering)->assertStatus(422);

        $unpaid = Transaction::create([
            'code' => 'BLK_REFUND_005',
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
            'grand_total' => 216000,
            'payment_status' => 'unpaid',
        ]);
        $this->cancelAs($this->sellerUser, $unpaid)->assertStatus(422);

        $this->assertSame([], $this->refundCalls);
        $this->assertSame('delivering', $delivering->fresh()->delivery_status);
    }

    public function test_other_sellers_and_the_buyer_cannot_cancel(): void
    {
        $transaction = $this->paidOrder('BLK_REFUND_006');

        $otherSeller = User::factory()->create();
        $otherSeller->assignRole('store');
        Store::create([
            'user_id' => $otherSeller->id,
            'name' => 'Other Store',
            'username' => 'otherstore',
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '0811111111',
            'address_id' => '1',
            'city' => 'Bandung',
            'address' => 'Jl. Lain',
            'postal_code' => '40111',
        ]);

        $this->cancelAs($otherSeller, $transaction)->assertStatus(403);
        $this->cancelAs($this->buyerUser, $transaction)->assertStatus(403);

        $this->assertSame('pending', $transaction->fresh()->delivery_status);
        $this->assertNull($transaction->fresh()->refund_status);
    }

    public function test_late_settlement_webhook_does_not_recredit_a_cancelled_order(): void
    {
        $this->gatewayResult = PaymentGatewayInterface::REFUND_MANUAL;
        $transaction = $this->paidOrder('BLK_REFUND_007');
        $this->cancelAs($this->sellerUser, $transaction)->assertOk();

        $this->webhook('BLK_REFUND_007', 'settlement')->assertOk();

        $this->assertEquals(0, (float) $this->storeBalance->fresh()->pending_balance);
        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertSame('manual_required', $transaction->fresh()->refund_status);

        // A refund notification (refund done from the Midtrans dashboard) closes it.
        $this->webhook('BLK_REFUND_007', 'refund', 'credit_card')->assertOk();
        $this->assertSame('refunded', $transaction->fresh()->refund_status);
    }

    public function test_buyer_cannot_submit_refund_account_when_none_is_owed(): void
    {
        $transaction = $this->paidOrder('BLK_REFUND_008');

        $this->actingAs($this->buyerUser)
            ->postJson("/api/transaction/{$transaction->id}/refund-account", [
                'refund_bank_name' => 'BCA',
                'refund_account_number' => '1234567890',
                'refund_account_name' => 'Pembeli Uji',
            ])
            ->assertStatus(422);
    }
}
