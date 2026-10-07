<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
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
use Tests\Support\FakePaymentGateway;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

/**
 * Checkout adds no tax: goods VAT belongs to PKP sellers inside their own
 * price, not to a marketplace surcharge. Earlier transactions keep the 11%
 * they were charged (B3.2b). Subtotal / grand-total arithmetic still runs
 * on Money to the persistence boundary.
 */
class CheckoutTaxMoneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->app->bind(ShippingGatewayInterface::class, fn () => new FakeShippingGateway);
        $this->app->bind(PaymentGatewayInterface::class, fn () => new FakePaymentGateway);
    }

    public function test_checkout_adds_no_tax_to_goods_or_shipping(): void
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id, 'name' => 'Toko Pajak', 'username' => 'toko-pajak',
            'logo' => 'default.png', 'about' => 'A', 'phone' => '0812',
            'address_id' => '1', 'city' => 'Jakarta', 'address' => 'Jl. S',
            'postal_code' => '12345', 'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-tax', 'description' => 'G']);

        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'P', 'slug' => 'p-tax-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => false, 'price' => 100_050, 'stock' => 10, 'weight' => 200,
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyerUser->buyer()->create([
            'phone_number' => '0898', 'city' => 'Bandung', 'address' => 'Jl. B',
        ]);

        $payload = [
            'address_id' => 101, 'address' => 'Jl. Kirim', 'city' => 'Surabaya',
            'postal_code' => '60000', 'shipping' => 'JNE', 'shipping_type' => 'REG',
            'products' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ];

        $this->actingAs($buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload)
            ->assertStatus(201);

        // shipping is 15_000 (FakeShippingGateway), service fee 1_000 (config)
        $this->assertDatabaseHas('transactions', [
            'store_id' => $store->id,
            'shipping_cost' => 15_000,
            'tax' => 0,
            'service_fee' => 1_000,
            'grand_total' => 100_050 + 15_000 + 1_000, // 116_050
        ]);
    }

    public function test_full_pipeline_capped_percentage_voucher_without_tax(): void
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id, 'name' => 'Toko Full', 'username' => 'toko-full',
            'logo' => 'default.png', 'about' => 'A', 'phone' => '0812',
            'address_id' => '1', 'city' => 'Jakarta', 'address' => 'Jl. S',
            'postal_code' => '12345', 'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-full', 'description' => 'G']);
        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'P', 'slug' => 'p-full-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => false, 'price' => 100_050, 'stock' => 10, 'weight' => 200,
        ]);

        // 25% of 100_050 = 25_012.5 but capped at 20_000
        Voucher::create([
            'code' => 'CAP20K', 'store_id' => null, 'type' => 'percentage',
            'value' => 25, 'max_discount' => 20_000, 'is_active' => true,
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = $buyerUser->buyer()->create([
            'phone_number' => '0898', 'city' => 'Bandung', 'address' => 'Jl. B',
        ]);

        $payload = [
            'address_id' => 101, 'address' => 'Jl. Kirim', 'city' => 'Surabaya',
            'postal_code' => '60000', 'shipping' => 'JNE', 'shipping_type' => 'REG',
            'voucher_code' => 'CAP20K',
            'products' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ];

        $this->actingAs($buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload)
            ->assertStatus(201)
            // subtotal 100_050 + shipping 15_000 - discount 20_000 + service fee 1_000
            // (the voucher never reduces the service fee)
            ->assertJsonPath('data.tax', 0)
            ->assertJsonPath('data.service_fee', 1_000)
            ->assertJsonPath('data.discount_amount', 20_000)
            ->assertJsonPath('data.grand_total', 96_050);

        $this->assertDatabaseHas('transactions', [
            'buyer_id' => $buyer->id,
            'tax' => 0,
            'service_fee' => 1_000,
            'discount_amount' => 20_000,
            'grand_total' => 96_050,
        ]);
    }
}
