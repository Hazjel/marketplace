<?php

namespace Tests\Feature;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\ExplodingRefundEscrowRepository;
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

    public function test_variant_stock_decrement_is_compensated_when_a_later_product_in_the_same_checkout_fails(): void
    {
        // MongoDB tidak ikut DB::beginTransaction() MySQL di create() --
        // koneksi terpisah, bukan distributed transaction. Produk varian
        // diproses PERTAMA (Mongo stock berkurang + commit ke Mongo),
        // produk kedua (non-varian, stok sengaja tidak cukup) diproses
        // SETELAHNYA dan gagal -- tanpa compensateMongoStock(),
        // DB::rollBack() cuma membatalkan sisi MySQL, decrement Mongo yang
        // sudah ter-apply tetap ada walau seluruh checkout gagal.
        $ctx = $this->checkoutContext();
        $lowStock = Product::create([
            'store_id' => $ctx['store']->id, 'product_category_id' => $ctx['product']->product_category_id,
            'name' => 'Stok Terbatas', 'slug' => 'stok-terbatas-'.uniqid(),
            'description' => 'D', 'condition' => 'new',
            'has_variants' => false, 'price' => 5000, 'stock' => 1, 'weight' => 100,
        ]);

        $payload = $this->basePayload() + [
            'products' => [
                // Varian diproses lebih dulu (urutan array = urutan loop
                // di TransactionRepository::create()) supaya Mongo-nya
                // benar-benar ter-decrement SEBELUM produk kedua gagal.
                ['product_id' => $ctx['product']->id, 'variant_id' => $ctx['variantMahal']->id, 'qty' => 2],
                ['product_id' => $lowStock->id, 'qty' => 5], // stok cuma 1 -- pasti gagal
            ],
        ];

        $this->actingAs($ctx['buyerUser'], 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload)
            ->assertStatus(500);

        $this->assertDatabaseCount('transaction_details', 0);
        $this->assertDatabaseMissing('transactions', ['buyer_id' => $ctx['buyer']->id]);

        // Baris paling penting -- SQL rollback otomatis Laravel tidak
        // pernah menyentuh Mongo, jadi ini yang membuktikan
        // compensateMongoStock() benar-benar bekerja.
        $this->assertSame(5, ProductVariantMongo::find($ctx['variantMahal']->id)->stock); // TIDAK berkurang
        $this->assertSame(15, $ctx['product']->fresh()->stock); // agregat juga tidak berkurang
        $this->assertSame(1, $lowStock->fresh()->stock);
    }

    /**
     * restoreStock() sendiri BUKAN lagi compensation boundary mandiri --
     * kalau ia sukses (Mongo variant stock sudah di-increment, SQL sudah
     * commit lewat DB::transaction() bersarangnya) tetapi operasi
     * SETELAHNYA di transaksi outer yang sama gagal (di sini: escrow
     * refund di TransactionRepository::updateStatus()), seluruh outer
     * transaction rollback -- termasuk stock_restored_at dan products.stock
     * SQL. Tanpa perbaikan ini, mutasi Mongo yang sudah dibuat restoreStock()
     * tetap permanen walau transaksinya sendiri batal dan stock_restored_at
     * tidak pernah tersimpan (transaksi masih "eligible" di-restore lagi).
     */
    /**
     * ProductVariantMongo casts price sebagai 'decimal:2' -- Laravel decimal
     * cast SELALU mengembalikan string ("150000.00"), bukan angka, baik di
     * PHP maupun (sebelum perbaikan ini) di JSON response ProductVariantResource.
     * Mobile mem-parsing price varian dengan int.tryParse() yang gagal untuk
     * string berdesimal ("150000.00" -> null -> fallback 0), jadi Product
     * Detail varian bisa tampil Rp0. ProductResource sudah menormalisasi ini
     * untuk products.price ((float)(string)$this->price); ProductVariantResource
     * dulu tidak.
     */
    public function test_variant_price_in_product_detail_response_is_numeric_not_a_decimal_string(): void
    {
        $ctx = $this->checkoutContext();

        $response = $this->getJson("/api/product/slug/{$ctx['product']->slug}");

        $response->assertStatus(200);
        $variants = collect($response->json('data.variants'));
        $this->assertCount(2, $variants);

        $mahal = $variants->firstWhere('id', (string) $ctx['variantMahal']->id);
        $this->assertNotNull($mahal, 'Varian mahal harus ada di response.');
        // json_encode() PHP membuang ".0" untuk float tanpa pecahan (tanpa
        // JSON_PRESERVE_ZERO_FRACTION), jadi 150000.0 bisa ke-decode balik
        // sebagai PHP int di sisi test -- yang penting BUKAN string decimal
        // seperti "150000.00" (bug aslinya), bukan soal int-vs-float.
        $this->assertIsNotString($mahal['price'], 'price varian harus numeric, bukan decimal string seperti "150000.00".');
        $this->assertEquals(150000, $mahal['price']);
        $this->assertIsInt($mahal['stock']);
        $this->assertSame(5, $mahal['stock']);
    }

    public function test_outer_transaction_failure_after_restore_stock_succeeds_still_compensates_mongo(): void
    {
        $ctx = $this->checkoutContext();

        $payload = $this->basePayload() + [
            'products' => [
                ['product_id' => $ctx['product']->id, 'variant_id' => $ctx['variantMahal']->id, 'qty' => 2],
            ],
        ];
        $checkoutResponse = $this->actingAs($ctx['buyerUser'], 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload);
        $checkoutResponse->assertStatus(201);

        $this->assertSame(3, ProductVariantMongo::find($ctx['variantMahal']->id)->stock); // 5 - 2
        $this->assertSame(13, $ctx['product']->fresh()->stock); // 15 - 2

        $transactionId = $checkoutResponse->json('data.id');

        // Delivery cancel + payment_status paid memicu updateStatus() ->
        // restoreStock() (Mongo 3 -> 5, SUKSES) lalu escrow refund (DILEDAKKAN
        // di sini oleh fake) -- seluruh method harus melempar, dan Mongo
        // harus balik ke 5, bukan tetap di angka hasil restoreStock().
        DB::table('transactions')->where('id', $transactionId)->update(['payment_status' => 'paid']);

        $this->app->bind(EscrowRepositoryInterface::class, fn () => new ExplodingRefundEscrowRepository);
        $repo = app(TransactionRepositoryInterface::class);

        $threw = false;
        try {
            $repo->updateStatus($transactionId, ['delivery_status' => 'cancelled']);
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertTrue($threw, 'updateStatus() seharusnya melempar exception dari refund escrow yang diledakkan');

        // Baris paling penting -- seluruh updateStatus() batal (SQL rollback,
        // assert di bawah membuktikannya), jadi Mongo harus kembali ke
        // state SEBELUM updateStatus() dipanggil (3), BUKAN tetap di 5
        // (hasil restoreStock() yang sukses sendiri, tapi jadi tidak
        // konsisten dengan SQL yang barusan di-rollback ke agregat 13).
        // Tanpa perbaikan ini Mongo tetap nyangkut di 5.
        $this->assertSame(3, ProductVariantMongo::find($ctx['variantMahal']->id)->stock);
        $this->assertSame(13, $ctx['product']->fresh()->stock); // SQL rollback: tetap agregat sebelum updateStatus()
        $this->assertNull(Transaction::find($transactionId)->stock_restored_at); // SQL rollback
        $this->assertSame('paid', Transaction::find($transactionId)->payment_status); // delivery_status/save() juga rollback
    }
}
