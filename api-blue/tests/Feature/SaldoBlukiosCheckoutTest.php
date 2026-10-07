<?php

namespace Tests\Feature;

use App\Events\TransactionStatusUpdated;
use App\Interfaces\BuyerBalanceRepositoryInterface;
use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransPaymentGateway;
use App\Support\OpsSignals;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

/**
 * S3: a checkout paid partly or fully with Saldo Blukios. Midtrans only ever
 * sees grand_total - balance_used; every unpaid-cancel path returns the
 * balance part once; a seller cancel returns it at once.
 *
 * Gadget order = 100.000 + 15.000 shipping + 1.000 fee = 116.000;
 * shirt order  =  50.000 + 15.000 + 1.000              =  66.000.
 */
class SaldoBlukiosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{order_id: string, gross: int}> */
    public array $snapCalls = [];

    /** @var list<int> refund amounts the fake gateway was asked for */
    public array $refundCalls = [];

    public ?string $snapToken = 'snap-token';

    private User $buyerUser;

    private Buyer $buyer;

    private Product $gadget;

    private Product $shirt;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->app->instance(PaymentGatewayInterface::class, new class($this) implements PaymentGatewayInterface
        {
            public function __construct(private SaldoBlukiosCheckoutTest $test) {}

            public function getSnapToken(Transaction $transaction): ?string
            {
                $this->test->snapCalls[] = [
                    'order_id' => $transaction->paymentCode(),
                    'gross' => Transaction::midtransTotal(Transaction::inPayment($transaction->paymentCode())->get())->minor(),
                ];

                return $this->test->snapToken;
            }

            public function refund(Transaction $transaction, string $reason): string
            {
                $this->test->refundCalls[] = (int) $transaction->refund_amount;

                return self::REFUND_DONE;
            }
        });
        $this->app->bind(ShippingGatewayInterface::class, fn () => new FakeShippingGateway);

        $category = ProductCategory::create(['name' => 'Umum', 'slug' => 'umum', 'description' => 'x']);
        $this->gadget = $this->product($this->store('gadgetstore'), $category, 'Gadget', 100000);
        $this->shirt = $this->product($this->store('shirtstore'), $category, 'Kemeja', 50000);

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->assignRole('buyer');
        $this->buyer = Buyer::create(['user_id' => $this->buyerUser->id, 'phone_number' => '0812']);
    }

    private function store(string $username): Store
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id,
            'name' => 'Toko '.$username,
            'username' => $username,
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '0812',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
        ]);
        StoreBalance::create(['store_id' => $store->id, 'balance' => 0, 'pending_balance' => 0]);

        return $store;
    }

    private function product(Store $store, ProductCategory $category, string $name, int $price): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'x',
            'condition' => 'new',
            'price' => $price,
            'weight' => 500,
            'stock' => 5,
        ]);
    }

    private function giveBalance(int $amount): void
    {
        app(BuyerBalanceRepositoryInterface::class)->credit($this->buyer->id, (string) $amount, BuyerBalanceHistory::TYPE_REFUND, 'seed:'.$amount);
    }

    private function balance(): int
    {
        return (int) $this->buyer->fresh()->balance;
    }

    /** @param  list<Product>  $products  one order per product (each its own store) */
    private function checkout(array $products, ?bool $useBalance = true)
    {
        $payload = [
            'address_id' => 1,
            'address' => 'Jl. Pembeli',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'orders' => array_map(fn (Product $p) => [
                'shipping' => 'JNE',
                'shipping_type' => 'REG',
                'products' => [['product_id' => $p->id, 'qty' => 1]],
            ], $products),
        ];
        if ($useBalance !== null) {
            $payload['use_balance'] = $useBalance;
        }

        return $this->actingAs($this->buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction/checkout', $payload);
    }

    private function webhook(string $orderId, int $gross, string $status = 'settlement')
    {
        $amount = $gross.'.00';

        return $this->postJson('/api/midtrans-callback', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $amount,
            'signature_key' => hash('sha512', $orderId.'200'.$amount.config('midtrans.serverKey')),
            'transaction_status' => $status,
            'payment_type' => 'qris',
        ]);
    }

    private function historyCount(string $type): int
    {
        return BuyerBalanceHistory::where('buyer_id', $this->buyer->id)->where('type', $type)->count();
    }

    private function sellerOf(Product $product): User
    {
        return User::findOrFail(Store::findOrFail($product->store_id)->user_id);
    }

    public function test_partial_balance_lowers_the_snap_amount_and_is_debited(): void
    {
        $this->giveBalance(50000);

        $response = $this->checkout([$this->gadget])->assertStatus(201);

        $order = Transaction::firstOrFail();
        $this->assertSame([['order_id' => $order->code, 'gross' => 66000]], $this->snapCalls);
        $this->assertSame(116000, (int) $order->grand_total);
        $this->assertSame(50000, (int) $order->balance_used);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(0, $this->balance());

        $history = BuyerBalanceHistory::where('unique_ref', 'payment:'.$order->id)->firstOrFail();
        $this->assertSame(BuyerBalanceHistory::TYPE_PAYMENT, $history->type);
        $this->assertSame(-50000, (int) $history->amount);
        $this->assertSame($order->id, $history->reference_id);

        $json = $response->json('data.0');
        $this->assertSame(50000, $json['balance_used']);
        $this->assertSame(66000, $json['midtrans_amount']);
        $this->assertFalse($json['paid_with_balance']);
        $this->assertSame('snap-token', $json['snap_token']);
    }

    public function test_balance_goes_to_orders_in_creation_order(): void
    {
        $this->giveBalance(130000);

        $this->checkout([$this->gadget, $this->shirt])->assertStatus(201);

        $gadgetOrder = Transaction::where('store_id', $this->gadget->store_id)->firstOrFail();
        $shirtOrder = Transaction::where('store_id', $this->shirt->store_id)->firstOrFail();
        $this->assertSame(116000, (int) $gadgetOrder->balance_used);
        $this->assertSame(14000, (int) $shirtOrder->balance_used);
        $this->assertSame([['order_id' => $gadgetOrder->payment_code, 'gross' => 52000]], $this->snapCalls);
        $this->assertSame(0, $this->balance());
        $this->assertSame(2, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT));
    }

    public function test_balance_covering_the_whole_payment_pays_it_without_midtrans(): void
    {
        $this->giveBalance(200000);

        $response = $this->checkout([$this->gadget, $this->shirt])->assertStatus(201);

        $this->assertSame([], $this->snapCalls);
        $this->assertSame(18000, $this->balance());
        foreach (Transaction::all() as $order) {
            $this->assertSame('paid', $order->payment_status);
            $this->assertNull($order->snap_token);
            $this->assertSame(1, DB::table('store_balance_histories')
                ->where('reference_id', $order->id)->where('type', 'pending_income')->count());
            // Escrow stays based on the full order value.
            $this->assertSame(
                (int) $order->grand_total - (int) $order->service_fee,
                (int) $order->seller_amount + (int) $order->admin_fee
            );
        }
        $this->assertSame([true, true], array_column($response->json('data'), 'paid_with_balance'));
        $this->assertSame([0, 0], array_column($response->json('data'), 'midtrans_amount'));

        // Paid: the expiry scheduler leaves them (and the balance) alone.
        DB::table('transactions')->update(['created_at' => now()->subMinutes(20)]);
        $this->artisan('transaction:check-expiry');
        $this->assertSame(['paid', 'paid'], Transaction::pluck('payment_status')->all());
        $this->assertSame(18000, $this->balance());
    }

    public function test_without_use_balance_checkout_is_unchanged(): void
    {
        $this->giveBalance(50000);

        $this->checkout([$this->gadget], null)->assertStatus(201);
        $this->checkout([$this->shirt], false)->assertStatus(201);

        $this->assertSame([116000, 66000], array_column($this->snapCalls, 'gross'));
        $this->assertSame([0, 0], Transaction::orderByDesc('grand_total')->get()->map(fn ($t) => (int) $t->balance_used)->all());
        $this->assertSame(50000, $this->balance());
        $this->assertSame(0, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT));
    }

    public function test_single_store_endpoint_uses_the_balance_too(): void
    {
        $this->giveBalance(50000);

        $this->actingAs($this->buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', [
                'address_id' => 1, 'address' => 'Jl. Pembeli', 'city' => 'Jakarta', 'postal_code' => '12345',
                'shipping' => 'JNE', 'shipping_type' => 'REG', 'use_balance' => true,
                'products' => [['product_id' => $this->gadget->id, 'qty' => 1]],
            ])->assertStatus(201)->assertJsonPath('data.midtrans_amount', 66000);

        $this->assertSame([66000], array_column($this->snapCalls, 'gross'));
    }

    public function test_zero_balance_debits_nothing(): void
    {
        $this->checkout([$this->gadget])->assertStatus(201);

        $this->assertSame([116000], array_column($this->snapCalls, 'gross'));
        $this->assertSame(0, (int) Transaction::firstOrFail()->balance_used);
        $this->assertSame(0, BuyerBalanceHistory::count());
    }

    public function test_sequential_checkouts_cannot_spend_the_balance_twice(): void
    {
        $this->giveBalance(116000);

        $this->checkout([$this->gadget])->assertStatus(201);
        $this->checkout([$this->shirt])->assertStatus(201);

        $shirtOrder = Transaction::where('store_id', $this->shirt->store_id)->firstOrFail();
        $this->assertSame(0, (int) $shirtOrder->balance_used);
        $this->assertSame([66000], array_column($this->snapCalls, 'gross'));
        $this->assertSame(0, $this->balance());
        $this->assertSame(1, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT));
    }

    public function test_webhook_amount_is_the_midtrans_part(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();

        $this->webhook($order->code, 116000)->assertStatus(403);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->webhook($order->code, 66000)->assertOk();
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        // Seller is paid on the full order value.
        $this->assertSame(115000, (int) $order->seller_amount + (int) $order->admin_fee);
        $this->assertSame((int) $order->seller_amount, (int) StoreBalance::where('store_id', $order->store_id)->value('pending_balance'));
    }

    public function test_webhook_failure_returns_the_balance_once(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();

        $this->webhook($order->code, 66000, 'expire')->assertOk();
        $this->webhook($order->code, 66000, 'cancel')->assertOk();

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(50000, $this->balance());
        $this->assertSame(1, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT_RETURNED));
        $this->assertSame(5, $this->gadget->fresh()->stock);
    }

    public function test_check_payment_status_failure_returns_the_balance_once(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();

        $mock = Mockery::mock('alias:Midtrans\Transaction');
        $mock->shouldReceive('status')->andReturn((object) [
            'transaction_status' => 'expire',
            'payment_type' => 'qris',
            'fraud_status' => null,
            'gross_amount' => '66000.00',
        ]);

        foreach ([1, 2] as $_) {
            $this->actingAs($this->buyerUser, 'sanctum')
                ->postJson("/api/transaction/{$order->id}/check-status")
                ->assertOk();
        }

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(50000, $this->balance());
        $this->assertSame(1, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT_RETURNED));
    }

    public function test_expiry_returns_the_balance_once(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        DB::table('transactions')->update(['created_at' => now()->subMinutes(20)]);

        $this->artisan('transaction:check-expiry');
        $this->artisan('transaction:check-expiry');

        $this->assertSame('failed', Transaction::firstOrFail()->payment_status);
        $this->assertSame(50000, $this->balance());
        $this->assertSame(1, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT_RETURNED));
    }

    public function test_deleting_an_unpaid_order_returns_the_balance(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();

        $repository = app(TransactionRepositoryInterface::class);
        $repository->delete($order->id);
        try {
            $repository->delete($order->id);
        } catch (\Exception) {
            // already gone
        }

        $this->assertSame(0, Transaction::count());
        $this->assertSame(50000, $this->balance());
        $this->assertSame(1, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT_RETURNED));
    }

    public function test_seller_cancel_returns_the_balance_part_and_refunds_only_the_rest(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();
        $this->webhook($order->code, 66000)->assertOk();

        $this->actingAs($this->sellerOf($this->gadget))
            ->postJson("/api/transaction/{$order->id}/cancel", ['reason' => 'Stok habis di gudang'])
            ->assertOk();

        $order->refresh();
        $this->assertSame(66000, (int) $order->refund_amount);
        $this->assertSame([66000], $this->refundCalls);
        $this->assertSame('refunded', $order->refund_status);
        $this->assertSame('midtrans', $order->refund_method);
        $this->assertSame(50000, $this->balance());
        $this->assertSame(1, BuyerBalanceHistory::where('unique_ref', 'refund_balance:'.$order->id)->count());
    }

    public function test_cancelling_a_fully_balance_paid_order_refunds_to_balance_without_a_job(): void
    {
        $this->giveBalance(116000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();
        $this->assertSame(0, $this->balance());

        Queue::fake();
        Event::fake([TransactionStatusUpdated::class]);
        $this->actingAs($this->sellerOf($this->gadget))
            ->postJson("/api/transaction/{$order->id}/cancel", ['reason' => 'Stok habis di gudang'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'refunded');

        Queue::assertNothingPushed();
        Event::assertDispatched(TransactionStatusUpdated::class, fn ($e) => $e->transaction->id === $order->id
            && $e->transaction->refund_status === 'refunded');
        $order->refresh();
        $this->assertSame(0, (int) $order->refund_amount);
        $this->assertSame('balance', $order->refund_method);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame(116000, $this->balance());
        $this->assertSame([], $this->refundCalls);
    }

    public function test_webhook_failure_of_a_shared_payment_returns_every_balance_part_once(): void
    {
        $this->giveBalance(130000);
        $this->checkout([$this->gadget, $this->shirt])->assertStatus(201);
        $paymentCode = (string) Transaction::firstOrFail()->payment_code;

        $this->webhook($paymentCode, 52000, 'expire')->assertOk();
        $this->webhook($paymentCode, 52000, 'expire')->assertOk();

        $this->assertSame(['failed', 'failed'], Transaction::pluck('payment_status')->all());
        $this->assertSame(130000, $this->balance());
        $this->assertSame(2, $this->historyCount(BuyerBalanceHistory::TYPE_PAYMENT_RETURNED));
        $this->assertSame([5, 5], [$this->gadget->fresh()->stock, $this->shirt->fresh()->stock]);
    }

    public function test_failed_order_does_not_move_back_to_unpaid(): void
    {
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();

        $this->webhook($order->code, 116000, 'expire')->assertOk();
        $this->webhook($order->code, 116000, 'pending')->assertOk();

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    private function expireAll(): void
    {
        DB::table('transactions')->update(['created_at' => now()->subMinutes(20)]);
        $this->artisan('transaction:check-expiry');
    }

    private function assertLateRefund(Transaction $order, int $amount): void
    {
        $order->refresh();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame($amount, (int) $order->refund_amount);
        $this->assertSame('Pembayaran masuk setelah pesanan kedaluwarsa', $order->refund_reason);
        // Sync queue: the job already ran against the fake gateway.
        $this->assertSame('refunded', $order->refund_status);
        $this->assertSame(0, DB::table('store_balance_histories')->where('reference_id', $order->id)->count());
    }

    public function test_late_settlement_webhook_refunds_instead_of_paying(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();
        $this->expireAll();
        $this->assertSame(50000, $this->balance());

        $this->webhook($order->code, 66000)->assertOk();
        $this->webhook($order->code, 66000)->assertOk();

        $this->assertLateRefund($order, 66000);
        $this->assertSame([66000], $this->refundCalls);
        $this->assertSame(50000, $this->balance());
        $this->assertSame(1, OpsSignals::count(OpsSignals::LATE_PAYMENT_REFUNDED));
    }

    public function test_late_settlement_webhook_on_a_shared_payment_refunds_each_midtrans_part(): void
    {
        $this->giveBalance(130000);
        $this->checkout([$this->gadget, $this->shirt])->assertStatus(201);
        $paymentCode = (string) Transaction::firstOrFail()->payment_code;
        $this->expireAll();

        $this->webhook($paymentCode, 52000)->assertOk();
        $this->webhook($paymentCode, 52000)->assertOk();

        // The gadget order was all Saldo Blukios, already returned: nothing owed.
        $gadgetOrder = Transaction::where('store_id', $this->gadget->store_id)->firstOrFail();
        $this->assertNull($gadgetOrder->refund_status);
        $this->assertSame('failed', $gadgetOrder->payment_status);
        $this->assertLateRefund(Transaction::where('store_id', $this->shirt->store_id)->firstOrFail(), 52000);
        $this->assertSame([52000], $this->refundCalls);
        $this->assertSame(130000, $this->balance());
    }

    private function mockMidtransStatus(string $status, int $gross): void
    {
        $mock = Mockery::mock('alias:Midtrans\Transaction');
        $mock->shouldReceive('status')->andReturn((object) [
            'transaction_status' => $status,
            'payment_type' => 'qris',
            'fraud_status' => null,
            'gross_amount' => $gross.'.00',
        ]);
    }

    public function test_late_settlement_on_manual_check_refunds_instead_of_paying(): void
    {
        $this->giveBalance(50000);
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();
        $this->expireAll();
        $this->mockMidtransStatus('settlement', 66000);

        foreach ([1, 2] as $_) {
            $this->actingAs($this->buyerUser, 'sanctum')
                ->postJson("/api/transaction/{$order->id}/check-status")
                ->assertOk();
        }

        $this->assertLateRefund($order, 66000);
        $this->assertSame([66000], $this->refundCalls);
        $this->assertSame(50000, $this->balance());
    }

    public function test_late_settlement_on_manual_check_of_a_shared_payment(): void
    {
        $this->giveBalance(130000);
        $this->checkout([$this->gadget, $this->shirt])->assertStatus(201);
        $shirtOrder = Transaction::where('store_id', $this->shirt->store_id)->firstOrFail();
        $this->expireAll();
        $this->mockMidtransStatus('settlement', 52000);

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$shirtOrder->id}/check-status")
            ->assertOk();

        $this->assertLateRefund($shirtOrder, 52000);
        $this->assertSame([52000], $this->refundCalls);
        $this->assertSame(130000, $this->balance());
    }

    public function test_manual_check_that_cannot_reach_midtrans_is_not_a_success(): void
    {
        $this->checkout([$this->gadget])->assertStatus(201);
        $order = Transaction::firstOrFail();
        $mock = Mockery::mock('alias:Midtrans\Transaction');
        $mock->shouldReceive('status')->andThrow(new \RuntimeException('Midtrans unreachable'));

        $this->actingAs($this->buyerUser, 'sanctum')
            ->postJson("/api/transaction/{$order->id}/check-status")
            ->assertStatus(502)
            ->assertJsonPath('success', false);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_no_snap_token_cancels_the_checkout_and_returns_the_balance(): void
    {
        $this->giveBalance(50000);
        $this->snapToken = null;

        $this->checkout([$this->gadget, $this->shirt])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Pembayaran gagal dibuat; Saldo Blukios sudah dikembalikan. Silakan coba lagi.');

        $this->assertSame(['failed', 'failed'], Transaction::pluck('payment_status')->all());
        $this->assertSame(50000, $this->balance());
        $this->assertSame([5, 5], [$this->gadget->fresh()->stock, $this->shirt->fresh()->stock]);
    }

    public function test_one_order_of_a_shared_payment_cannot_be_deleted(): void
    {
        $this->checkout([$this->gadget, $this->shirt])->assertStatus(201);

        try {
            app(TransactionRepositoryInterface::class)->delete(Transaction::firstOrFail()->id);
            $this->fail('deleting one order of a shared payment must be refused');
        } catch (\Exception $e) {
            $this->assertSame(422, $e->getCode());
        }

        $this->assertSame(2, Transaction::count());
    }

    public function test_midtrans_refund_asks_for_the_refund_amount_not_grand_total(): void
    {
        $order = new Transaction([
            'code' => 'BLKREFUNDPART',
            'grand_total' => 116000,
            'balance_used' => 50000,
            'refund_amount' => 66000,
        ]);

        $mock = Mockery::mock('alias:Midtrans\Transaction');
        $mock->shouldReceive('status')->andReturn((object) [
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ]);
        $mock->shouldReceive('refund')->once()
            ->with('BLKREFUNDPART', Mockery::on(fn (array $params) => $params['amount'] === 66000));

        $this->assertSame(PaymentGatewayInterface::REFUND_DONE, (new MidtransPaymentGateway)->refund($order, 'Stok habis'));
    }
}
