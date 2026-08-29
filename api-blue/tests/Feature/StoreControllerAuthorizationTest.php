<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * StoreController::store()/update()/destroy()/updateVerifiedStatus() dulu
 * tidak punya pengecekan kepemilikan sama sekali (update/destroy) atau tidak
 * ada pengecekan izin sama sekali (updateVerifiedStatus), dan store() percaya
 * user_id dari client mentah-mentah. 'store-create'/'store-edit' dipegang
 * SEMUA seller (role 'store' di RoleSeeder), bukan admin-only, jadi tanpa
 * perbaikan ini seller mana pun bisa membuat/mengubah/menghapus toko
 * KOMPETITOR, atau memverifikasi toko sendiri/orang lain tanpa jadi admin.
 */
class StoreControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function seller(): array
    {
        $user = User::factory()->create();
        $user->assignRole('store');
        // is_verified di-pin false -- factory random 70% true, dan test
        // verifikasi di bawah butuh state awal yang deterministik.
        $store = Store::factory()->create(['user_id' => $user->id, 'is_verified' => false]);

        return [$user, $store];
    }

    public function test_seller_cannot_use_admin_store_create_endpoint(): void
    {
        // KOREKSI: fix sebelumnya memaksa user_id ke pemanggil, yang
        // secara diam-diam mengizinkan seller membuat TOKO KEDUA (domain
        // model ini single-store per user -- User::store() hasOne, tanpa
        // unique constraint di DB). Endpoint ini sekarang admin-only;
        // register-store() tetap satu-satunya jalur onboarding seller.
        [$sellerA] = $this->seller(); // sellerA SUDAH punya store
        $victim = User::factory()->create();

        $response = $this->actingAs($sellerA, 'sanctum')->postJson('/api/store', [
            'user_id' => $victim->id,
            'name' => 'Toko Titipan',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'about' => 'Deskripsi',
            'phone' => '081200000000',
            'address_id' => 1,
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('stores', ['name' => 'Toko Titipan']);
    }

    public function test_seller_with_deactivated_store_cannot_create_replacement_store(): void
    {
        [$seller, $store] = $this->seller();
        $store->update(['is_active' => false]); // mis. dinonaktifkan admin

        // Role 'store' (dan permission 'store-create'-nya) tidak dicabut
        // bareng is_active -- tanpa fix ini, seller yang toko-nya
        // dinonaktifkan bisa langsung bikin toko baru lewat endpoint ini.
        $this->actingAs($seller, 'sanctum')->postJson('/api/store', [
            'user_id' => $seller->id,
            'name' => 'Toko Pengganti',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'about' => 'Deskripsi',
            'phone' => '081200000000',
            'address_id' => 1,
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('stores', ['name' => 'Toko Pengganti']);
    }

    public function test_admin_cannot_create_second_store_for_a_user_who_already_has_one(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$seller] = $this->seller(); // sudah punya satu store

        $this->actingAs($admin, 'sanctum')->postJson('/api/store', [
            'user_id' => $seller->id,
            'name' => 'Toko Kedua Dari Admin',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'about' => 'Deskripsi',
            'phone' => '081200000000',
            'address_id' => 1,
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345',
        ])->assertStatus(409);

        $this->assertDatabaseMissing('stores', ['name' => 'Toko Kedua Dari Admin']);
    }

    public function test_register_store_blocks_a_second_store_created_via_admin_path(): void
    {
        // Cross-path hole: admin POST /api/store membuat Store + StoreBalance
        // untuk target user_id, tapi TIDAK memberi role 'store' ke user itu
        // (assignRole('store') cuma ada di registerStore()). Guard lama di
        // registerStore() cuma cek hasRole('store') -- buyer yang toko-nya
        // dibuatkan admin masih lolos guard itu dan bisa registerStore()
        // lagi, menghasilkan toko kedua. Source of truth-nya sekarang
        // keberadaan row Store (user->store()->exists()), bukan role.
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $buyer = User::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/store', [
            'user_id' => $buyer->id,
            'name' => 'Toko Dibuatkan Admin',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'about' => 'Deskripsi',
            'phone' => '081200000000',
            'address_id' => 1,
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345',
        ])->assertStatus(201);

        // Buyer belum punya role 'store' -- konfirmasi premis skenario ini.
        $this->assertFalse($buyer->fresh()->hasRole('store'));

        $this->actingAs($buyer, 'sanctum')->postJson('/api/register-store', [
            'name' => 'Toko Kedua Buyer',
            'phone' => '081200000001',
        ])->assertStatus(400);

        $this->assertSame(1, Store::where('user_id', $buyer->id)->count());
        $this->assertDatabaseMissing('stores', ['name' => 'Toko Kedua Buyer']);
    }

    public function test_seller_cannot_update_another_sellers_store(): void
    {
        [$sellerA, $storeA] = $this->seller();
        [, $storeB] = $this->seller();

        $response = $this->actingAs($sellerA, 'sanctum')->putJson("/api/store/{$storeB->id}", [
            'name' => 'Dibajak Seller A',
            'about' => 'x', 'phone' => '081200000001',
            'address_id' => 1, 'city' => 'Jakarta', 'address' => 'x', 'postal_code' => '12345',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('stores', ['id' => $storeB->id, 'name' => 'Dibajak Seller A']);

        // Kontrol positif -- seller yang sama boleh update tokonya sendiri.
        $this->actingAs($sellerA, 'sanctum')->putJson("/api/store/{$storeA->id}", [
            'name' => 'Nama Baru',
            'about' => 'x', 'phone' => '081200000001',
            'address_id' => 1, 'city' => 'Jakarta', 'address' => 'x', 'postal_code' => '12345',
        ])->assertStatus(200);
    }

    public function test_seller_cannot_destroy_another_sellers_store(): void
    {
        [$sellerA] = $this->seller();
        [, $storeB] = $this->seller();

        $this->actingAs($sellerA, 'sanctum')
            ->deleteJson("/api/store/{$storeB->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('stores', ['id' => $storeB->id, 'is_active' => true]);
    }

    public function test_store_destroy_deactivates_and_preserves_other_buyers_transaction(): void
    {
        // 'store-delete' cuma dipegang admin (tidak ada di daftar permission
        // role 'store' di RoleSeeder) -- destroy() sudah tergerbang di level
        // permission untuk non-admin, jadi caller di sini admin, bukan
        // pemilik toko sendiri. Ini tetap menguji hal yang penting: caller
        // yang BOLEH menghapus (admin) tidak berakhir dengan hard-delete
        // yang mencaplok data transaksi pembeli lain.
        [, $store] = $this->seller();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $storeBalance = StoreBalance::create(['store_id' => $store->id, 'balance' => 0, 'pending_balance' => 50000]);

        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-store-destroy', 'description' => 'G']);
        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Barang Uji Hapus Toko', 'slug' => 'barang-uji-hapus-toko',
            'description' => 'D', 'condition' => 'new', 'price' => 100000, 'weight' => 1000, 'stock' => 10,
        ]);

        $otherBuyerUser = User::factory()->create();
        $otherBuyerUser->assignRole('buyer');
        $otherBuyer = Buyer::factory()->create(['user_id' => $otherBuyerUser->id]);
        $transaction = Transaction::create([
            'code' => 'STORE_DESTROY_001', 'buyer_id' => $otherBuyer->id, 'store_id' => $store->id,
            'address_id' => 1, 'address' => 'x', 'city' => 'Jakarta', 'postal_code' => '12345',
            'shipping' => 'JNE', 'shipping_type' => 'REG', 'shipping_cost' => 15000,
            'tax' => 11000, 'grand_total' => 126000, 'payment_status' => 'paid',
        ]);
        TransactionDetail::create([
            'transaction_id' => $transaction->id, 'product_id' => $product->id, 'qty' => 1, 'subtotal' => 100000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/store/{$store->id}")
            ->assertStatus(200);

        // Deaktivasi, bukan hard delete -- baris-baris ini tidak boleh lenyap.
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_active' => false]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('store_balances', ['id' => $storeBalance->id]);
        $this->assertDatabaseHas('buyers', ['id' => $otherBuyer->id]);

        // Toko nonaktif harus hilang dari storefront publik.
        $this->getJson("/api/store/{$store->id}")->assertJson(['success' => true, 'data' => null]);
    }

    public function test_store_destroy_flushes_the_product_listing_cache(): void
    {
        // KOREKSI catatan sebelumnya: product/all/paginated SUDAH aktif
        // (routes/api.php:65), bukan dead code seperti yang salah
        // disimpulkan di commit 547b78e0 -- cache 600 detik-nya live.
        // getAll() query-nya sendiri sudah benar memfilter
        // store.is_active, jadi satu-satunya cara produk toko nonaktif
        // tetap muncul di sini adalah lewat CACHE yang belum di-flush.
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$seller, $store] = $this->seller();

        $parent = ProductCategory::create(['name' => 'G', 'slug' => 'g-cache-flush', 'description' => 'G']);
        $category = ProductCategory::create([
            'name' => 'H', 'slug' => 'h-cache-flush', 'description' => 'H', 'parent_id' => $parent->id,
        ]);
        Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Produk Sebelum Nonaktif', 'slug' => 'produk-sebelum-nonaktif',
            'description' => 'D', 'condition' => 'new', 'price' => 10000, 'weight' => 100, 'stock' => 1,
        ]);

        // Warm cache-nya selagi toko masih aktif.
        $this->getJson('/api/product/all/paginated?row_per_page=10')
            ->assertJsonFragment(['name' => 'Produk Sebelum Nonaktif']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/store/{$store->id}")->assertStatus(200);

        // Tanpa flush, ini akan tetap mengembalikan hasil cache lama
        // (produk yang sekarang harusnya sudah tersembunyi).
        $this->getJson('/api/product/all/paginated?row_per_page=10')
            ->assertJsonMissing(['name' => 'Produk Sebelum Nonaktif']);
    }

    public function test_non_admin_cannot_verify_a_store(): void
    {
        [$sellerA] = $this->seller();
        [, $storeB] = $this->seller();

        $this->actingAs($sellerA, 'sanctum')
            ->postJson("/api/store/{$storeB->id}/verified")
            ->assertStatus(403);

        $this->assertDatabaseHas('stores', ['id' => $storeB->id, 'is_verified' => false]);
    }

    public function test_admin_can_verify_a_store(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [, $store] = $this->seller();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/store/{$store->id}/verified")
            ->assertStatus(200);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_verified' => true]);
    }
}
