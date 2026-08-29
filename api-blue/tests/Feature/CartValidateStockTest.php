<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CartRepository::validateStock() dulu fail OPEN untuk produk bervarian:
 * variant_id null, variant tidak ditemukan, atau Mongo down semuanya jatuh
 * balik ke $product->stock (agregat -- total stok lintas SEMUA varian),
 * bukan stok varian spesifik. Efeknya varian yang sudah habis tetap lolos
 * validasi selama agregat produk masih > 0. Kontrak baru: fail CLOSED.
 */
class CartValidateStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function context(): array
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id, 'name' => 'Toko Varian', 'username' => 'toko-validate-stock',
            'logo' => 'default.png', 'about' => 'About', 'phone' => '08123456789',
            'address_id' => '1', 'city' => 'Jakarta', 'address' => 'Jl. Seller',
            'postal_code' => '12345', 'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-validate-stock', 'description' => 'G']);

        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Kaos Variasi', 'slug' => 'kaos-variasi-vs-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => true, 'price' => 100000, 'stock' => 15, 'weight' => 200,
        ]);

        $variantHabis = ProductVariantMongo::create([
            'product_id' => $product->id, 'name' => 'Habis/S',
            'variant_attributes' => ['Ukuran' => 'S'],
            'price' => 100000, 'stock' => 0, 'sku' => 'KV-HABIS-S',
        ]);
        $variantAda = ProductVariantMongo::create([
            'product_id' => $product->id, 'name' => 'Ada/L',
            'variant_attributes' => ['Ukuran' => 'L'],
            'price' => 150000, 'stock' => 5, 'sku' => 'KV-ADA-L',
        ]);

        $otherProduct = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Produk Lain', 'slug' => 'produk-lain-vs-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => false, 'price' => 20000, 'stock' => 3, 'weight' => 100,
        ]);

        $foreignVariant = ProductVariantMongo::create([
            'product_id' => $otherProduct->id, 'name' => 'Bukan Punya Product Ini',
            'variant_attributes' => [], 'price' => 10000, 'stock' => 99, 'sku' => 'FOREIGN',
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyerUser->buyer()->create(['phone_number' => '08987654321', 'city' => 'Bandung', 'address' => 'Jl. Buyer']);

        return compact('product', 'variantHabis', 'variantAda', 'otherProduct', 'foreignVariant', 'buyerUser');
    }

    public function test_variant_product_without_variant_id_is_invalid(): void
    {
        $ctx = $this->context();

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->postJson('/api/cart/validate-stock', [
            'items' => [
                ['product_id' => $ctx['product']->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $item = $response->json('data.items.0');
        $this->assertFalse($item['valid']);
        $this->assertSame('variant_required', $item['reason']);
        $this->assertFalse($response->json('data.all_valid'));
    }

    public function test_nonexistent_variant_id_is_invalid_not_silently_using_aggregate_stock(): void
    {
        $ctx = $this->context();

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->postJson('/api/cart/validate-stock', [
            'items' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => 'does-not-exist', 'quantity' => 1],
            ],
        ]);

        $item = $response->json('data.items.0');
        $this->assertFalse($item['valid']);
        $this->assertSame('variant_not_found', $item['reason']);
    }

    public function test_variant_belonging_to_a_different_product_is_rejected(): void
    {
        $ctx = $this->context();

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->postJson('/api/cart/validate-stock', [
            'items' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => (string) $ctx['foreignVariant']->id, 'quantity' => 1],
            ],
        ]);

        $item = $response->json('data.items.0');
        $this->assertFalse($item['valid']);
        $this->assertSame('invalid_variant', $item['reason']);
    }

    public function test_out_of_stock_variant_is_invalid_despite_positive_aggregate_stock(): void
    {
        $ctx = $this->context();

        // products.stock = 15 (agregat), tapi varian ini stoknya 0 --
        // bug lama lolos karena fallback ke agregat.
        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->postJson('/api/cart/validate-stock', [
            'items' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => (string) $ctx['variantHabis']->id, 'quantity' => 1],
            ],
        ]);

        $item = $response->json('data.items.0');
        $this->assertFalse($item['valid']);
        $this->assertSame('insufficient_stock', $item['reason']);
        $this->assertSame(0, $item['available']);
    }

    public function test_valid_variant_with_enough_stock_passes(): void
    {
        $ctx = $this->context();

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->postJson('/api/cart/validate-stock', [
            'items' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => (string) $ctx['variantAda']->id, 'quantity' => 3],
            ],
        ]);

        $item = $response->json('data.items.0');
        $this->assertTrue($item['valid']);
        $this->assertNull($item['reason']);
        $this->assertSame(5, $item['available']);
        $this->assertTrue($response->json('data.all_valid'));
    }

    public function test_non_variant_product_still_uses_aggregate_stock(): void
    {
        $ctx = $this->context();

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->postJson('/api/cart/validate-stock', [
            'items' => [
                ['product_id' => $ctx['otherProduct']->id, 'quantity' => 2],
            ],
        ]);

        $item = $response->json('data.items.0');
        $this->assertTrue($item['valid']);
        $this->assertSame(3, $item['available']);
    }

    public function test_unknown_product_id_is_invalid(): void
    {
        $ctx = $this->context();

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->postJson('/api/cart/validate-stock', [
            'items' => [
                ['product_id' => (string) Str::uuid(), 'quantity' => 1],
            ],
        ]);

        $item = $response->json('data.items.0');
        $this->assertFalse($item['valid']);
        $this->assertSame('product_not_found', $item['reason']);
    }
}
