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

/**
 * B3.2b: checkout tax is now `Money->percentage(1100)` (11% PPN, HALF_UP,
 * on the product subtotal only — shipping is not taxed), and subtotal /
 * grand-total arithmetic runs on Money to the persistence boundary.
 *
 * This pins the rounding at a subtotal whose 11% lands exactly on a .5
 * tie, so a regression to truncation or banker's rounding is caught.
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
    }

    public function test_tax_is_eleven_percent_half_up_and_shipping_is_untaxed(): void
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

        // 100_050 * 11% = 11_005.5  -> HALF_UP -> 11_006
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

        // shipping is 15_000 (FakeShippingGateway) and must not be taxed
        $this->assertDatabaseHas('transactions', [
            'store_id' => $store->id,
            'shipping_cost' => 15_000,
            'tax' => 11_006,
            'grand_total' => 100_050 + 11_006 + 15_000, // 126_056
        ]);
    }

    public function test_full_pipeline_odd_tax_plus_capped_percentage_voucher(): void
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
            // subtotal 100_050 + tax 11_006 + shipping 15_000 - discount 20_000
            ->assertJsonPath('data.tax', 11_006)
            ->assertJsonPath('data.discount_amount', 20_000)
            ->assertJsonPath('data.grand_total', 106_056);

        $this->assertDatabaseHas('transactions', [
            'buyer_id' => $buyer->id,
            'tax' => 11_006,
            'discount_amount' => 20_000,
            'grand_total' => 106_056,
        ]);
    }
}
