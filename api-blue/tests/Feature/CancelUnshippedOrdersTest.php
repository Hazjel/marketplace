<?php

namespace Tests\Feature;

use App\Console\Commands\CancelUnshippedOrders;
use App\Events\TransactionStatusUpdated;
use App\Interfaces\BuyerBalanceRepositoryInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Jobs\RefundCancelledTransactionJob;
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
use App\Services\MidtransPaymentGateway;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

class CancelUnshippedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $sellerUser;

    private Store $store;

    private StoreBalance $storeBalance;

    private User $buyerUser;

    private Buyer $buyer;

    private Product $product;

    private Carbon $paidAt;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // Refunds wait in the queue; these tests are about what the cancel leaves.
        Queue::fake();

        $this->sellerUser = User::factory()->create();
        $this->sellerUser->assignRole('store');

        $this->store = Store::create([
            'user_id' => $this->sellerUser->id,
            'name' => 'Ship Deadline Store',
            'username' => 'shipdeadline',
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
            'stock' => 40,
            'weight' => 0.5,
            'condition' => 'new',
        ]);

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->assignRole('buyer');
        $this->buyer = Buyer::create([
            'user_id' => $this->buyerUser->id,
            'phone_number' => '08987654321',
        ]);

        $this->paidAt = Carbon::create(2026, 11, 2, 9, 0, 0);
        $this->travelTo($this->paidAt);
    }

    private function unpaidOrder(string $code, string $deliveryStatus = 'pending', float $balanceUsed = 0): Transaction
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
            'balance_used' => $balanceUsed,
            'payment_status' => 'unpaid',
            'delivery_status' => $deliveryStatus,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'subtotal' => 200000,
        ]);

        return $transaction;
    }

    // Paid through the real webhook path: markPaid() sets the deadline and escrow holds the seller's share.
    private function paidOrder(string $code, string $deliveryStatus = 'pending', float $balanceUsed = 0): Transaction
    {
        $transaction = $this->unpaidOrder($code, $deliveryStatus, $balanceUsed);

        $midtrans = 216000 - $balanceUsed;
        if ($midtrans > 0) {
            $this->webhook($code, number_format($midtrans, 2, '.', ''))->assertOk();
        } else {
            // Saldo covered it all: checkout's payWithBalance() marks it paid, no webhook.
            DB::transaction(fn () => app(TransactionRepositoryInterface::class)->markPaid($transaction));
        }

        return $transaction->fresh();
    }

    private function webhook(string $code, string $gross = '216000.00')
    {
        $signature = hash('sha512', $code.'200'.$gross.config('midtrans.serverKey'));

        return $this->postJson('/api/midtrans-callback', [
            'order_id' => $code,
            'status_code' => '200',
            'gross_amount' => $gross,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ]);
    }

    private function runCommand(): void
    {
        $this->artisan('transaction:cancel-unshipped')->assertSuccessful();
    }

    private function pastDeadline(): void
    {
        $this->travelTo($this->paidAt->copy()->addDays(3)->addMinute());
    }

    private function ledgerCount(Transaction $transaction, string $type): int
    {
        return StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', $type)->count();
    }

    private function assertAutoCancelled(Transaction $transaction): void
    {
        $transaction->refresh();
        $this->assertSame('cancelled', $transaction->delivery_status);
        $this->assertSame('failed', $transaction->payment_status);
        $this->assertSame(CancelUnshippedOrders::REASON, $transaction->refund_reason);
        $this->assertSame(1, $this->ledgerCount($transaction, StoreBalanceHistory::TYPE_REFUNDED));
    }

    public function test_paying_sets_paid_at_and_a_deadline_that_a_duplicate_webhook_does_not_move(): void
    {
        $transaction = $this->paidOrder('BLK_SHIP_PAID');

        $this->assertTrue($transaction->paid_at->eq($this->paidAt));
        $this->assertTrue($transaction->ship_deadline_at->eq($this->paidAt->copy()->addDays(3)));

        $this->travel(2)->hours();
        $this->webhook('BLK_SHIP_PAID')->assertOk();

        $transaction->refresh();
        $this->assertTrue($transaction->paid_at->eq($this->paidAt));
        $this->assertTrue($transaction->ship_deadline_at->eq($this->paidAt->copy()->addDays(3)));
        $this->assertSame(1, $this->ledgerCount($transaction, StoreBalanceHistory::TYPE_PENDING_INCOME));
    }

    public function test_the_manual_status_check_sets_the_deadline_once(): void
    {
        $transaction = $this->unpaidOrder('BLK_SHIP_STATUS');
        $this->mock(MidtransPaymentGateway::class)->shouldReceive('getPaymentStatus')->andReturn((object) [
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'fraud_status' => null,
            'gross_amount' => '216000.00',
        ]);

        $this->actingAs($this->buyerUser, 'sanctum')->postJson("/api/transaction/{$transaction->id}/check-status")->assertOk();
        $transaction->refresh();
        $this->assertSame('paid', $transaction->payment_status);
        $this->assertTrue($transaction->paid_at->eq($this->paidAt));
        $this->assertTrue($transaction->ship_deadline_at->eq($this->paidAt->copy()->addDays(3)));

        $this->travel(2)->hours();
        $this->actingAs($this->buyerUser, 'sanctum')->postJson("/api/transaction/{$transaction->id}/check-status")->assertOk();
        $transaction->refresh();
        $this->assertTrue($transaction->paid_at->eq($this->paidAt));
        $this->assertTrue($transaction->ship_deadline_at->eq($this->paidAt->copy()->addDays(3)));
        $this->assertSame(1, $this->ledgerCount($transaction, StoreBalanceHistory::TYPE_PENDING_INCOME));
    }

    public function test_a_checkout_paid_fully_with_saldo_sets_the_deadline(): void
    {
        $this->app->bind(ShippingGatewayInterface::class, fn () => new FakeShippingGateway);
        app(BuyerBalanceRepositoryInterface::class)->credit($this->buyer->id, '500000', BuyerBalanceHistory::TYPE_REFUND, 'seed:ship-deadline');

        $this->actingAs($this->buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction/checkout', [
                'address_id' => 1,
                'address' => 'Jl. Buyer',
                'city' => 'Jakarta',
                'postal_code' => '12345',
                'use_balance' => true,
                'orders' => [[
                    'shipping' => 'JNE',
                    'shipping_type' => 'REG',
                    'products' => [['product_id' => $this->product->id, 'qty' => 1]],
                ]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.0.paid_with_balance', true);

        $transaction = Transaction::sole();
        $this->assertSame('paid', $transaction->payment_status);
        $this->assertTrue($transaction->paid_at->eq($this->paidAt));
        $this->assertTrue($transaction->ship_deadline_at->eq($this->paidAt->copy()->addDays(3)));
    }

    public function test_overdue_pending_and_processing_orders_are_cancelled_and_refunded(): void
    {
        $pending = $this->paidOrder('BLK_SHIP_PENDING');
        $processing = $this->paidOrder('BLK_SHIP_PROCESSING', 'processing');
        $this->assertGreaterThan(0, (float) $this->storeBalance->fresh()->pending_balance);

        Event::fake([TransactionStatusUpdated::class]);
        $this->pastDeadline();
        $this->runCommand();

        foreach ([$pending, $processing] as $transaction) {
            $this->assertAutoCancelled($transaction);
            $this->assertSame('processing', $transaction->refund_status);
            $this->assertEquals(216000, (float) $transaction->refund_amount);
        }

        Queue::assertPushed(RefundCancelledTransactionJob::class, 2);
        Event::assertDispatched(TransactionStatusUpdated::class, 2);
        $this->assertEquals(0, (float) $this->storeBalance->fresh()->pending_balance);
        $this->assertEquals(44, $this->product->fresh()->stock);
    }

    public function test_an_order_paid_fully_with_saldo_is_refunded_to_saldo_right_away(): void
    {
        $transaction = $this->paidOrder('BLK_SHIP_SALDO', 'pending', 216000);

        $this->pastDeadline();
        $this->runCommand();

        $this->assertAutoCancelled($transaction);
        $this->assertSame('refunded', $transaction->refund_status);
        $this->assertSame('balance', $transaction->refund_method);
        $this->assertEquals(216000, (float) $this->buyer->fresh()->balance);
        Queue::assertNotPushed(RefundCancelledTransactionJob::class);
    }

    public function test_a_split_payment_returns_saldo_now_and_refunds_only_the_midtrans_part(): void
    {
        $transaction = $this->paidOrder('BLK_SHIP_SPLIT', 'pending', 16000);

        $this->pastDeadline();
        $this->runCommand();

        $this->assertAutoCancelled($transaction);
        $this->assertSame('processing', $transaction->refund_status);
        $this->assertEquals(200000, (float) $transaction->refund_amount);
        $this->assertEquals(16000, (float) $this->buyer->fresh()->balance);
        Queue::assertPushed(RefundCancelledTransactionJob::class, 1);
    }

    public function test_only_paid_unshipped_orders_at_or_past_their_deadline_are_cancelled(): void
    {
        $atDeadline = $this->paidOrder('BLK_SHIP_AT');
        $shipped = $this->paidOrder('BLK_SHIP_SENT', 'processing');
        $this->travel(1)->second();
        $notYet = $this->paidOrder('BLK_SHIP_NOT_YET');

        $this->actingAs($this->sellerUser)->putJson("/api/transaction/{$shipped->id}", [
            'delivery_status' => 'delivering',
            'tracking_number' => 'JNE-SHIP-1',
        ])->assertOk();

        $unpaid = $this->paidOrder('BLK_SHIP_UNPAID');
        $unpaid->forceFill(['payment_status' => 'unpaid', 'ship_deadline_at' => $this->paidAt])->save();

        $this->travelTo($this->paidAt->copy()->addDays(3));
        $this->runCommand();

        $this->assertAutoCancelled($atDeadline);
        $this->assertSame('pending', $notYet->fresh()->delivery_status);
        $this->assertSame('delivering', $shipped->fresh()->delivery_status);
        $this->assertSame('unpaid', $unpaid->fresh()->payment_status);
        $this->assertNull($unpaid->fresh()->refund_status);
    }

    public function test_an_order_without_a_deadline_gets_a_full_one_instead_of_being_cancelled(): void
    {
        $transaction = $this->paidOrder('BLK_SHIP_LEGACY', 'processing');
        $transaction->forceFill(['paid_at' => null, 'ship_deadline_at' => null])->save();

        $this->pastDeadline();
        $this->runCommand();

        $transaction->refresh();
        $this->assertSame('processing', $transaction->delivery_status);
        $this->assertNull($transaction->paid_at);
        $this->assertTrue($transaction->ship_deadline_at->eq(now()->addDays(3)));
    }

    public function test_a_second_run_and_a_late_webhook_do_not_refund_twice(): void
    {
        $transaction = $this->paidOrder('BLK_SHIP_TWICE');

        $this->pastDeadline();
        $this->runCommand();
        $this->runCommand();
        $this->webhook('BLK_SHIP_TWICE')->assertOk();
        $this->assertSame(0, StoreBalanceHistory::where('reference_id', $transaction->id)->where('type', StoreBalanceHistory::TYPE_RELEASED)->count());

        $this->assertAutoCancelled($transaction);
        $this->assertSame('processing', $transaction->refund_status);
        Queue::assertPushed(RefundCancelledTransactionJob::class, 1);
        $this->assertSame(1, $this->ledgerCount($transaction, StoreBalanceHistory::TYPE_PENDING_INCOME));
        $this->assertEquals(42, $this->product->fresh()->stock);
    }

    public function test_an_order_that_keeps_failing_does_not_block_the_others(): void
    {
        $broken = $this->paidOrder('BLK_SHIP_BROKEN');
        $healthy = $this->paidOrder('BLK_SHIP_HEALTHY');
        // EscrowRepository::refund() throws 422 without the pending ledger.
        StoreBalanceHistory::where('reference_id', $broken->id)->delete();

        Log::spy();
        $this->pastDeadline();
        $this->runCommand();

        $this->assertAutoCancelled($healthy);
        $this->assertSame('pending', $broken->fresh()->delivery_status);
        $this->assertSame('paid', $broken->fresh()->payment_status);
        Log::shouldHaveReceived('log')->with('error', 'transaction:cancel-unshipped failed', Mockery::on(
            fn (array $context) => $context['transaction'] === $broken->id
        ))->once();
    }

    public function test_a_failing_notification_is_logged_without_undoing_or_repeating_the_refund(): void
    {
        $transaction = $this->paidOrder('BLK_SHIP_NOTIFY');
        Event::listen(TransactionStatusUpdated::class, fn () => throw new RuntimeException('Reverb down'));

        Log::spy();
        $this->pastDeadline();
        $this->runCommand();

        $this->assertAutoCancelled($transaction);
        Queue::assertPushed(RefundCancelledTransactionJob::class, 1);
        Log::shouldHaveReceived('error')->with('transaction:cancel-unshipped notify failed', Mockery::any())->once();
    }

    public function test_shipping_requires_a_resi_and_stores_no_proof_when_it_is_missing(): void
    {
        Storage::fake('public');
        $transaction = $this->paidOrder('BLK_SHIP_NO_RESI', 'processing');

        $this->actingAs($this->sellerUser)->post("/api/transaction/{$transaction->id}", [
            '_method' => 'PUT',
            'delivery_status' => 'delivering',
            'delivery_proof' => UploadedFile::fake()->image('proof.png'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nomor resi wajib diisi');

        $this->assertSame('processing', $transaction->fresh()->delivery_status);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_older_shipped_order_without_a_resi_can_still_update_its_proof(): void
    {
        Storage::fake('public');
        $transaction = $this->paidOrder('BLK_SHIP_LEGACY_SENT', 'delivering');

        $this->actingAs($this->sellerUser)->post("/api/transaction/{$transaction->id}", [
            '_method' => 'PUT',
            'delivery_status' => 'delivering',
            'delivery_proof' => UploadedFile::fake()->image('proof.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNotNull($transaction->fresh()->delivery_proof);
    }

    public function test_updating_an_auto_cancelled_order_says_it_was_cancelled(): void
    {
        $transaction = $this->paidOrder('BLK_SHIP_LATE_SELLER', 'processing');
        $this->pastDeadline();
        $this->runCommand();

        $this->actingAs($this->sellerUser)->putJson("/api/transaction/{$transaction->id}", [
            'delivery_status' => 'delivering',
            'tracking_number' => 'JNE-TOO-LATE',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan sudah dibatalkan');
    }
}
