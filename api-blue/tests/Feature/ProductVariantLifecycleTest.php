<?php

namespace Tests\Feature;

use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

/**
 * ProductRepository::update() menghapus varian Mongo secara PERMANEN kalau
 * variant_id-nya tidak ikut dikirim di request edit. Kalau varian itu masih
 * direferensikan transaction_details dari order yang belum terminal (belum
 * stock_restored_at, belum completed), pembatalan/kedaluwarsa order itu
 * nanti memanggil restoreStock() -> ProductVariantMongo::find() -> null ->
 * SKIP restorasi diam-diam. products.stock (agregat, dihitung ulang dari
 * varian yang TERSISA) jadi permanen lebih rendah dari seharusnya, tanpa
 * error yang terlihat di mana pun.
 */
class ProductVariantLifecycleTest extends TestCase
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

    private function context(): array
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id, 'name' => 'Toko Varian', 'username' => 'toko-lifecycle',
            'logo' => 'default.png', 'about' => 'About', 'phone' => '08123456789',
            'address_id' => '1', 'city' => 'Jakarta', 'address' => 'Jl. Seller',
            'postal_code' => '12345', 'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-lifecycle', 'description' => 'G']);

        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Kaos Variasi', 'slug' => 'kaos-variasi-lc-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => true, 'price' => 100000, 'stock' => 15, 'weight' => 200,
        ]);

        $variantMurah = ProductVariantMongo::create([
            'product_id' => $product->id, 'name' => 'Merah/S',
            'variant_attributes' => ['Ukuran' => 'S'],
            'price' => 100000, 'stock' => 10, 'sku' => 'KV-MERAH-S',
        ]);
        $variantMahal = ProductVariantMongo::create([
            'product_id' => $product->id, 'name' => 'Biru/L',
            'variant_attributes' => ['Ukuran' => 'L'],
            'price' => 150000, 'stock' => 5, 'sku' => 'KV-BIRU-L',
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = $buyerUser->buyer()->create([
            'phone_number' => '08987654321', 'city' => 'Bandung', 'address' => 'Jl. Buyer',
        ]);

        return compact('store', 'category', 'product', 'variantMurah', 'variantMahal', 'buyer', 'buyerUser');
    }

    private function checkoutVariantMahal(array $ctx, int $qty = 1): string
    {
        $payload = [
            'address_id' => 101, 'address' => 'Jl. Pengiriman', 'city' => 'Surabaya',
            'postal_code' => '60000', 'shipping' => 'JNE', 'shipping_type' => 'REG',
            'products' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => $ctx['variantMahal']->id, 'qty' => $qty],
            ],
        ];

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload);
        $response->assertStatus(201);

        return $response->json('data.id');
    }

    private function updatePayload(array $ctx, array $variants): array
    {
        return [
            'store_id' => $ctx['store']->id,
            'product_category_id' => $ctx['category']->id,
            'name' => $ctx['product']->name,
            'description' => 'D',
            'condition' => 'new',
            'price' => 100000,
            'weight' => 200,
            'stock' => 15,
            'variants' => $variants,
        ];
    }

    public function test_deleting_a_variant_referenced_by_a_pending_order_is_rejected(): void
    {
        $ctx = $this->context();
        $this->checkoutVariantMahal($ctx); // pending order references variantMahal, stock_restored_at null

        // Kirim hanya variantMurah -- variantMahal TIDAK ikut, artinya
        // "hapus" dari sudut pandang update().
        $payload = $this->updatePayload($ctx, [
            ['id' => (string) $ctx['variantMurah']->id, 'name' => 'Merah/S', 'price' => 100000, 'stock' => 10],
        ]);

        $repo = app(ProductRepositoryInterface::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tidak bisa menghapus varian yang masih direferensikan pesanan yang belum selesai. Selesaikan atau batalkan pesanan tersebut terlebih dahulu.');

        try {
            $repo->update($ctx['product']->id, $payload);
        } finally {
            // Varian TIDAK terhapus, apa pun hasil exception-nya.
            $this->assertNotNull(ProductVariantMongo::find($ctx['variantMahal']->id));
        }
    }

    public function test_deleting_a_variant_is_allowed_once_its_order_is_completed(): void
    {
        $ctx = $this->context();
        $transactionId = $this->checkoutVariantMahal($ctx);

        Transaction::where('id', $transactionId)->update(['delivery_status' => 'completed']);

        $payload = $this->updatePayload($ctx, [
            ['id' => (string) $ctx['variantMurah']->id, 'name' => 'Merah/S', 'price' => 100000, 'stock' => 10],
        ]);

        app(ProductRepositoryInterface::class)->update($ctx['product']->id, $payload);

        $this->assertNull(ProductVariantMongo::find($ctx['variantMahal']->id));
        $this->assertNotNull(ProductVariantMongo::find($ctx['variantMurah']->id));
    }

    public function test_deleting_a_variant_is_allowed_once_its_order_stock_was_already_restored(): void
    {
        $ctx = $this->context();
        $transactionId = $this->checkoutVariantMahal($ctx);

        Transaction::where('id', $transactionId)->update(['stock_restored_at' => now()]);

        $payload = $this->updatePayload($ctx, [
            ['id' => (string) $ctx['variantMurah']->id, 'name' => 'Merah/S', 'price' => 100000, 'stock' => 10],
        ]);

        app(ProductRepositoryInterface::class)->update($ctx['product']->id, $payload);

        $this->assertNull(ProductVariantMongo::find($ctx['variantMahal']->id));
    }

    public function test_deleting_an_unreferenced_variant_is_unaffected(): void
    {
        $ctx = $this->context();
        // Tidak ada checkout sama sekali -- kedua varian bebas direferensikan.

        $payload = $this->updatePayload($ctx, [
            ['id' => (string) $ctx['variantMurah']->id, 'name' => 'Merah/S', 'price' => 100000, 'stock' => 10],
        ]);

        app(ProductRepositoryInterface::class)->update($ctx['product']->id, $payload);

        $this->assertNull(ProductVariantMongo::find($ctx['variantMahal']->id));
        $this->assertNotNull(ProductVariantMongo::find($ctx['variantMurah']->id));
    }
}
