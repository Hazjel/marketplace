<?php

namespace Tests\Feature;

use App\Interfaces\ShippingGatewayInterface;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        // Checkout menanyakan ongkir ke gateway; jangan sentuh Komerce asli.
        $this->app->bind(ShippingGatewayInterface::class, fn () => new FakeShippingGateway);
    }

    private function makeStoreAndProduct(int $price = 10000, int $stock = 10): array
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');

        $store = Store::create([
            'user_id' => $seller->id,
            'name' => 'Seller Store',
            'username' => 'sellerstore-'.Str::random(6),
            'logo' => 'default.png',
            'about' => 'About',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Seller',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        $category = ProductCategory::create([
            'name' => 'General', 'slug' => 'general-'.Str::random(6), 'description' => 'General',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-'.Str::random(6),
            'description' => 'Test Desc',
            'price' => $price,
            'stock' => $stock,
            'weight' => 1,
            'condition' => 'new',
        ]);

        return [$store, $product];
    }

    private function makeBuyer(): array
    {
        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyerProfile = $buyerUser->buyer()->create([
            'phone_number' => '08987654321',
            'city' => 'Bandung',
            'address' => 'Jl. Buyer',
        ]);

        return [$buyerUser, $buyerProfile];
    }

    private function checkoutPayload($store, $product, int $qty = 1, ?string $voucherCode = null): array
    {
        $payload = [
            'store_id' => $store->id,
            'address_id' => 101,
            'address' => 'Jl. Pengiriman',
            'city' => 'Surabaya',
            'postal_code' => '60000',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'products' => [
                ['product_id' => $product->id, 'qty' => $qty],
            ],
        ];

        if ($voucherCode !== null) {
            $payload['voucher_code'] = $voucherCode;
        }

        return $payload;
    }

    public function test_validate_endpoint_returns_discount_for_valid_code()
    {
        [$store, $product] = $this->makeStoreAndProduct();
        [$buyerUser, $buyerProfile] = $this->makeBuyer();

        $voucher = Voucher::create([
            'code' => 'HEMAT10',
            'store_id' => null,
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 5000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($buyerUser, 'sanctum')
            ->postJson('/api/voucher/validate', [
                'code' => 'HEMAT10',
                'store_id' => $store->id,
                'subtotal' => 100000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.voucher_id', $voucher->id)
            ->assertJsonPath('data.discount_amount', 5000);
    }

    public function test_validate_endpoint_rejects_below_min_purchase()
    {
        [$store, $product] = $this->makeStoreAndProduct();
        [$buyerUser, $buyerProfile] = $this->makeBuyer();

        Voucher::create([
            'code' => 'MIN50K',
            'store_id' => null,
            'type' => 'fixed',
            'value' => 5000,
            'min_purchase' => 50000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($buyerUser, 'sanctum')
            ->postJson('/api/voucher/validate', [
                'code' => 'MIN50K',
                'store_id' => $store->id,
                'subtotal' => 10000,
            ]);

        $response->assertStatus(422);
    }

    public function test_checkout_with_valid_voucher_applies_discount_and_records_redemption()
    {
        [$store, $product] = $this->makeStoreAndProduct(price: 100000);
        [$buyerUser, $buyerProfile] = $this->makeBuyer();

        $voucher = Voucher::create([
            'code' => 'DISKON20K',
            'store_id' => null,
            'type' => 'fixed',
            'value' => 20000,
            'is_active' => true,
        ]);

        $payload = $this->checkoutPayload($store, $product, qty: 1, voucherCode: 'DISKON20K');
        $payload['buyer_id'] = $buyerProfile->id;

        $response = $this->actingAs($buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload);

        $response->assertStatus(201);

        // subtotal 100000, tax 11% = 11000, shipping 15000 => 126000 - 20000 discount = 106000
        $response->assertJsonPath('data.discount_amount', 20000)
            ->assertJsonPath('data.grand_total', 106000)
            ->assertJsonPath('data.voucher_id', $voucher->id);

        $this->assertDatabaseHas('voucher_redemptions', [
            'voucher_id' => $voucher->id,
            'buyer_id' => $buyerProfile->id,
        ]);
    }

    public function test_checkout_ignores_voucher_that_exceeds_usage_limit_per_buyer()
    {
        [$store, $product] = $this->makeStoreAndProduct(price: 100000, stock: 10);
        [$buyerUser, $buyerProfile] = $this->makeBuyer();

        $voucher = Voucher::create([
            'code' => 'SEKALIPAKAI',
            'store_id' => null,
            'type' => 'fixed',
            'value' => 10000,
            'usage_limit_per_buyer' => 1,
            'is_active' => true,
        ]);

        $payload = $this->checkoutPayload($store, $product, qty: 1, voucherCode: 'SEKALIPAKAI');
        $payload['buyer_id'] = $buyerProfile->id;

        // First redemption succeeds.
        $first = $this->actingAs($buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload);
        $first->assertStatus(201)->assertJsonPath('data.voucher_id', $voucher->id);

        // Second attempt with the same buyer must NOT get a second discount —
        // the repository re-validates server-side and silently drops an
        // already-exhausted voucher rather than trusting the client.
        $second = $this->actingAs($buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload);
        $second->assertStatus(201)
            ->assertJsonPath('data.voucher_id', null)
            ->assertJsonPath('data.discount_amount', 0);

        $this->assertDatabaseCount('voucher_redemptions', 1);
    }
}
