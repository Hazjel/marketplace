<?php

namespace Tests\Feature;

use App\Interfaces\ShippingGatewayInterface;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

/**
 * products.price adalah harga varian TERMURAH (ProductRepository::create()/
 * update() -- collect($variants)->min('price')), bukan harga produk yang
 * sesungguhnya. Checkout dulu cuma menerima product_id+qty (tanpa
 * variant_id), jadi TransactionDetailRepository selalu memakai
 * products.price -- membeli varian mana pun selalu ditagih harga varian
 * TERMURAH, dan stok yang berkurang cuma agregat products.stock, bukan
 * stok varian spesifik (Mongo) -- varian yang sudah habis tetap "bisa
 * dibeli" selama agregat produk masih > 0.
 */
class VariantCheckoutTest extends TestCase
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

    private function checkoutContext(): array
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id, 'name' => 'Toko Varian', 'username' => 'toko-varian',
            'logo' => 'default.png', 'about' => 'About', 'phone' => '08123456789',
            'address_id' => '1', 'city' => 'Jakarta', 'address' => 'Jl. Seller',
            'postal_code' => '12345', 'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-variant-checkout', 'description' => 'G']);

        // Produk bervarian: products.price/stock adalah agregat (min price,
        // sum stock) persis seperti ProductRepository::create() betulan --
        // dibuat manual di sini supaya test tidak perlu upload gambar.
        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Kaos Variasi', 'slug' => 'kaos-variasi-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => true, 'price' => 100000, 'stock' => 15, 'weight' => 200,
        ]);

        $variantMurah = ProductVariantMongo::create([
            'product_id' => $product->id, 'name' => 'Merah/S',
            'variant_attributes' => ['Warna' => 'Merah', 'Ukuran' => 'S'],
            'price' => 100000, 'stock' => 10, 'sku' => 'KV-MERAH-S',
        ]);
        $variantMahal = ProductVariantMongo::create([
            'product_id' => $product->id, 'name' => 'Biru/L',
            'variant_attributes' => ['Warna' => 'Biru', 'Ukuran' => 'L'],
            'price' => 150000, 'stock' => 5, 'sku' => 'KV-BIRU-L',
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = $buyerUser->buyer()->create([
            'phone_number' => '08987654321', 'city' => 'Bandung', 'address' => 'Jl. Buyer',
        ]);

        return compact('store', 'product', 'variantMurah', 'variantMahal', 'buyer', 'buyerUser');
    }

    private function basePayload(): array
    {
        return [
            'address_id' => 101,
            'address' => 'Jl. Pengiriman',
            'city' => 'Surabaya',
            'postal_code' => '60000',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
        ];
    }

    public function test_checkout_charges_the_selected_variants_price_not_the_cheapest(): void
    {
        $ctx = $this->checkoutContext();

        $payload = $this->basePayload() + [
            'products' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => $ctx['variantMahal']->id, 'qty' => 1],
            ],
        ];

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])->postJson('/api/transaction', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transaction_details', [
            'product_id' => $ctx['product']->id,
            'variant_id' => (string) $ctx['variantMahal']->id,
            'subtotal' => 150000, // varian mahal, BUKAN 100000 (harga varian termurah)
        ]);
    }

    public function test_checkout_rejects_variant_product_without_variant_id(): void
    {
        $ctx = $this->checkoutContext();

        $payload = $this->basePayload() + [
            'products' => [
                ['product_id' => $ctx['product']->id, 'qty' => 1], // tidak ada variant_id sama sekali
            ],
        ];

        // 500, bukan 400/422 -- TransactionController::store() memanggil
        // ResponseHelper::exceptionResponse($e) TANPA argumen $code kedua
        // (default 500), sama seperti exception checkout lain di repository
        // ini ("Insufficient stock for product", dst). Konsisten dengan
        // konvensi yang sudah ada, bukan status code baru yang dipilih sendiri.
        $this->actingAs($ctx['buyerUser'], 'sanctum')->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])->postJson('/api/transaction', $payload)
            ->assertStatus(500);

        $this->assertDatabaseCount('transaction_details', 0);
    }

    public function test_checkout_decrements_only_the_purchased_variants_stock(): void
    {
        $ctx = $this->checkoutContext();

        $payload = $this->basePayload() + [
            'products' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => $ctx['variantMahal']->id, 'qty' => 2],
            ],
        ];

        $this->actingAs($ctx['buyerUser'], 'sanctum')->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])->postJson('/api/transaction', $payload)
            ->assertStatus(201);

        $this->assertSame(3, ProductVariantMongo::find($ctx['variantMahal']->id)->stock); // 5 - 2
        $this->assertSame(10, ProductVariantMongo::find($ctx['variantMurah']->id)->stock); // tidak tersentuh
        $this->assertSame(13, $ctx['product']->fresh()->stock); // agregat: 15 - 2
    }

    public function test_checkout_rejects_when_specific_variant_is_out_of_stock_even_if_product_aggregate_is_not(): void
    {
        $ctx = $this->checkoutContext();
        // Habiskan stok variantMahal secara langsung (mis. sudah kejual di
        // transaksi lain) -- agregat products.stock (15) TIDAK ikut diubah
        // di sini, sengaja, supaya premis "agregat masih > 0" persis seperti
        // yang dijelaskan di komentar class ini.
        $ctx['variantMahal']->update(['stock' => 0]);

        $payload = $this->basePayload() + [
            'products' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => $ctx['variantMahal']->id, 'qty' => 1],
            ],
        ];

        $this->actingAs($ctx['buyerUser'], 'sanctum')->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])->postJson('/api/transaction', $payload)
            ->assertStatus(500);

        $this->assertDatabaseCount('transaction_details', 0);
    }

    public function test_non_variant_product_checkout_still_works_unchanged(): void
    {
        $ctx = $this->checkoutContext();
        $simple = Product::create([
            'store_id' => $ctx['store']->id, 'product_category_id' => $ctx['product']->product_category_id,
            'name' => 'Produk Tanpa Varian', 'slug' => 'produk-tanpa-varian-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => false, 'price' => 20000, 'stock' => 5, 'weight' => 100,
        ]);

        $payload = $this->basePayload() + [
            'products' => [
                ['product_id' => $simple->id, 'qty' => 2],
            ],
        ];

        $response = $this->actingAs($ctx['buyerUser'], 'sanctum')->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])->postJson('/api/transaction', $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('transaction_details', [
            'product_id' => $simple->id,
            'variant_id' => null,
            'subtotal' => 40000,
        ]);
        $this->assertSame(3, $simple->fresh()->stock);
    }

    public function test_expiry_scheduler_restores_the_specific_variants_stock(): void
    {
        // restoreStock() sebelumnya cuma mengembalikan agregat products.stock
        // -- pembatalan/expiry pesanan yang membeli varian tertentu tidak
        // pernah mengembalikan stok varian spesifik itu, jadi stok Mongo-nya
        // hilang permanen walau pesanannya batal.
        $ctx = $this->checkoutContext();

        $payload = $this->basePayload() + [
            'products' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => $ctx['variantMahal']->id, 'qty' => 2],
            ],
        ];
        $this->actingAs($ctx['buyerUser'], 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload)
            ->assertStatus(201);

        $this->assertSame(3, ProductVariantMongo::find($ctx['variantMahal']->id)->stock); // 5 - 2
        $this->assertSame(13, $ctx['product']->fresh()->stock); // 15 - 2

        // Paksa transaksi terlihat kedaluwarsa (>15 menit) supaya scheduler mengambilnya.
        DB::table('transactions')
            ->where('buyer_id', $ctx['buyer']->id)
            ->update(['created_at' => now()->subMinutes(20), 'payment_status' => 'unpaid']);

        $this->artisan('transaction:check-expiry');

        $this->assertSame(5, ProductVariantMongo::find($ctx['variantMahal']->id)->stock); // dikembalikan penuh
        $this->assertSame(15, $ctx['product']->fresh()->stock);
    }
}
