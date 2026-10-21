<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Models\Buyer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

/**
 * Orders from several stores paid with one Midtrans payment: one transaction
 * per store, sharing payment_code (the Midtrans order_id) and one Snap token.
 */
class MultiStoreCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{order_id: string, gross: int}> */
    public array $snapCalls = [];

    private User $buyerUser;

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
            public function __construct(private MultiStoreCheckoutTest $test) {}

            public function getSnapToken(Transaction $transaction): ?string
            {
                $this->test->snapCalls[] = [
                    'order_id' => $transaction->paymentCode(),
                    'gross' => (int) Transaction::inPayment($transaction->paymentCode())->sum('grand_total'),
                ];

                return 'snap-token';
            }

            public function refund(Transaction $transaction, string $reason): string
            {
                return self::REFUND_DONE;
            }

            public function expirePayment(string $orderId): void {}
        });
        $this->app->bind(ShippingGatewayInterface::class, fn () => new FakeShippingGateway);

        $category = ProductCategory::create(['name' => 'Umum', 'slug' => 'umum', 'description' => 'x']);
        $this->gadget = $this->product($this->store('gadgetstore'), $category, 'Gadget', 100000);
        $this->shirt = $this->product($this->store('shirtstore'), $category, 'Kemeja', 50000);

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->assignRole('buyer');
        Buyer::create(['user_id' => $this->buyerUser->id, 'phone_number' => '0812']);
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

    private function checkout(array $orders)
    {
        return $this->actingAs($this->buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction/checkout', [
                'address_id' => 1,
                'address' => 'Jl. Pembeli',
                'city' => 'Jakarta',
                'postal_code' => '12345',
                'orders' => $orders,
            ]);
    }

    private function order(Product $product, int $qty): array
    {
        return [
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'products' => [['product_id' => $product->id, 'qty' => $qty]],
        ];
    }

    private function webhook(string $orderId, int $gross)
    {
        $amount = $gross.'.00';

        return $this->postJson('/api/midtrans-callback', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $amount,
            'signature_key' => hash('sha512', $orderId.'200'.$amount.config('midtrans.serverKey')),
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ]);
    }

    /**
     * Existing rows deliberately occupy the complete legacy suffix space.
     *
     * @param  list<string>  $codes
     * @param  list<string>  $paymentCodes
     */
    private function seedTransactionIdentifiers(array $codes = [], array $paymentCodes = []): void
    {
        $now = now();
        $rows = [];

        foreach ($codes as $code) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'code' => $code,
                'payment_code' => null,
                'buyer_id' => $this->buyerUser->buyer->id,
                'store_id' => $this->gadget->store_id,
                'address_id' => '1',
                'address' => 'Jl. Seed',
                'city' => 'Jakarta',
                'postal_code' => '12345',
                'shipping' => 'JNE',
                'shipping_type' => 'REG',
                'shipping_cost' => 0,
                'tax' => 0,
                'grand_total' => 0,
                'payment_status' => 'unpaid',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($paymentCodes as $paymentCode) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'code' => 'SEED-'.$paymentCode,
                'payment_code' => $paymentCode,
                'buyer_id' => $this->buyerUser->buyer->id,
                'store_id' => $this->gadget->store_id,
                'address_id' => '1',
                'address' => 'Jl. Seed',
                'city' => 'Jakarta',
                'postal_code' => '12345',
                'shipping' => 'JNE',
                'shipping_type' => 'REG',
                'shipping_cost' => 0,
                'tax' => 0,
                'grand_total' => 0,
                'payment_status' => 'unpaid',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('transactions')->insert($rows);
    }

    public function test_order_code_never_collides_with_an_existing_order(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 21, 12, 34, 56));

        $second = str_pad((string) now()->format('dmYHis'), 12, '0', STR_PAD_LEFT);
        $existingCodes = array_map(
            function (int $suffix) use ($second): string {
                return 'BLK'.$second.$suffix;
            },
            range(10, 99)
        );
        $this->seedTransactionIdentifiers($existingCodes);

        $response = $this->checkout([$this->order($this->gadget, 1)])->assertStatus(201);
        $code = (string) $response->json('data.0.code');

        $this->assertStringStartsWith('BLK', $code);
        $this->assertLessThanOrEqual(50, strlen($code));
        $this->assertNotContains($code, $existingCodes);
        $this->assertSame(1, Transaction::where('code', $code)->count());
    }

    public function test_multi_store_payment_code_never_collides_with_existing_payment_code_or_order_code(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 21, 12, 34, 56));

        $second = str_pad((string) now()->format('dmYHis'), 12, '0', STR_PAD_LEFT);
        $existingPaymentCodes = array_map(
            function (int $suffix) use ($second): string {
                return 'BLKP'.$second.$suffix;
            },
            range(100, 999)
        );
        $this->seedTransactionIdentifiers(
            $existingPaymentCodes,
            $existingPaymentCodes
        );

        $response = $this->checkout([
            $this->order($this->gadget, 1),
            $this->order($this->shirt, 1),
        ])->assertStatus(201);
        $paymentCode = (string) $response->json('data.0.payment_code');

        $this->assertStringStartsWith('BLKP', $paymentCode);
        $this->assertLessThanOrEqual(50, strlen($paymentCode));
        $this->assertNotContains($paymentCode, $existingPaymentCodes);
        $this->assertSame(
            2,
            Transaction::where('payment_code', $paymentCode)->count()
        );
        $this->assertSame(
            0,
            Transaction::where('code', $paymentCode)->count()
        );
    }

    public function test_one_order_per_store_sharing_one_payment(): void
    {
        $this->checkout([$this->order($this->gadget, 1), $this->order($this->shirt, 1)])->assertStatus(201);

        $orders = Transaction::orderByDesc('grand_total')->get();
        $this->assertCount(2, $orders);
        $this->assertSame([$this->gadget->store_id, $this->shirt->store_id], $orders->pluck('store_id')->all());

        // Each order is a normal transaction: its goods + 15.000 shipping + 1.000 fee.
        $this->assertSame([116000, 66000], $orders->map(fn ($t) => (int) $t->grand_total)->all());

        $paymentCode = $orders->first()->payment_code;
        $this->assertStringStartsWith('BLKP', (string) $paymentCode);
        $this->assertSame([$paymentCode, $paymentCode], $orders->pluck('payment_code')->all());
        $this->assertSame(['snap-token', 'snap-token'], $orders->pluck('snap_token')->all());

        // One Snap for the whole payment, for the sum of both orders.
        $this->assertSame([['order_id' => $paymentCode, 'gross' => 182000]], $this->snapCalls);
    }

    public function test_settlement_pays_every_order_and_credits_each_store(): void
    {
        $this->checkout([$this->order($this->gadget, 1), $this->order($this->shirt, 1)])->assertStatus(201);

        $this->webhook(Transaction::first()->payment_code, 182000)->assertOk();

        $this->assertSame(['paid', 'paid'], Transaction::pluck('payment_status')->all());
        foreach ([$this->gadget->store_id, $this->shirt->store_id] as $storeId) {
            $this->assertGreaterThan(0, (float) StoreBalance::where('store_id', $storeId)->value('pending_balance'));
        }
    }

    public function test_the_webhook_amount_must_cover_the_whole_payment(): void
    {
        $this->checkout([$this->order($this->gadget, 1), $this->order($this->shirt, 1)])->assertStatus(201);

        // One order's amount is not the payment's amount.
        $this->webhook(Transaction::first()->payment_code, 116000)->assertStatus(403);

        $this->assertSame(0, Transaction::where('payment_status', 'paid')->count());
    }

    public function test_one_failing_order_cancels_the_whole_checkout(): void
    {
        $response = $this->checkout([$this->order($this->gadget, 1), $this->order($this->shirt, 99)]);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame(0, Transaction::count());
        $this->assertSame(5, $this->gadget->fresh()->stock, 'the first order took no stock');
        $this->assertSame([], $this->snapCalls);
    }

    public function test_a_single_store_checkout_still_pays_under_its_own_code(): void
    {
        $this->checkout([$this->order($this->gadget, 1)])->assertStatus(201);

        $order = Transaction::firstOrFail();
        $this->assertNull($order->payment_code);
        $this->assertSame([['order_id' => $order->code, 'gross' => 116000]], $this->snapCalls);

        $this->webhook($order->code, 116000)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }
}
