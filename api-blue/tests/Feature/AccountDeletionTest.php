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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * stores.user_id dan buyers.user_id cascade ke users, dan dari sana cascade
 * lagi ke products, transactions, store_balances, store_balance_histories,
 * dan withdrawals. Hard-delete satu baris user bisa memusnahkan seluruh
 * toko, katalog, riwayat transaksi, dan ledger saldo penjual sekaligus --
 * termasuk transaksi milik pembeli LAIN yang pernah beli di toko itu.
 *
 * User sekarang pakai SoftDeletes, yang tidak pernah mengeluarkan SQL
 * DELETE, jadi FK cascade di atas tidak pernah terpicu. Toko ditandai
 * is_active=false secara terpisah supaya berhenti tampil/dibeli tanpa ikut
 * kehilangan riwayat transaksinya.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function sellerWithHistory(): array
    {
        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('store');
        $store = Store::factory()->create(['user_id' => $sellerUser->id]);
        $storeBalance = StoreBalance::create([
            'store_id' => $store->id,
            'balance' => 0,
            'pending_balance' => 50000,
        ]);
        $storeBalance->storeBalanceHistories()->create([
            'type' => 'pending_income',
            'amount' => 50000,
            'remarks' => 'uji',
        ]);

        $category = ProductCategory::create([
            'name' => 'General', 'slug' => 'general-deletion', 'description' => 'General',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Barang Uji Hapus Akun',
            'slug' => 'barang-uji-hapus-akun',
            'description' => 'Deskripsi',
            'condition' => 'new',
            'price' => 100000,
            'weight' => 1000,
            'stock' => 10,
        ]);

        $otherBuyerUser = User::factory()->create();
        $otherBuyerUser->assignRole('buyer');
        $otherBuyer = Buyer::factory()->create(['user_id' => $otherBuyerUser->id]);

        $transaction = Transaction::create([
            'code' => 'DEL_TEST_001',
            'buyer_id' => $otherBuyer->id,
            'store_id' => $store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'tax' => 11000,
            'grand_total' => 126000,
            'payment_status' => 'paid',
        ]);
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return compact('sellerUser', 'store', 'storeBalance', 'product', 'otherBuyer', 'transaction');
    }

    public function test_deleting_a_sellers_account_preserves_store_products_and_financial_history(): void
    {
        $ctx = $this->sellerWithHistory();

        $this->actingAs($ctx['sellerUser'], 'sanctum')
            ->deleteJson('/api/profile')
            ->assertStatus(200);

        // Baris-baris ini tidak boleh ikut lenyap -- itulah seluruh maksud
        // memakai soft-delete alih-alih hard-delete di sini.
        $this->assertDatabaseHas('stores', ['id' => $ctx['store']->id]);
        $this->assertDatabaseHas('products', ['id' => $ctx['product']->id]);
        $this->assertDatabaseHas('transactions', ['id' => $ctx['transaction']->id]);
        $this->assertDatabaseHas('store_balances', ['id' => $ctx['storeBalance']->id]);
        $this->assertSame(
            1,
            DB::table('store_balance_histories')->where('store_balance_id', $ctx['storeBalance']->id)->count()
        );

        // Transaksi pembeli LAIN yang pernah belanja di toko ini juga harus
        // tetap utuh -- bukan cuma data milik akun yang dihapus.
        $this->assertDatabaseHas('buyers', ['id' => $ctx['otherBuyer']->id]);
    }

    public function test_deleted_sellers_store_is_deactivated(): void
    {
        $ctx = $this->sellerWithHistory();

        $this->actingAs($ctx['sellerUser'], 'sanctum')->deleteJson('/api/profile');

        $this->assertFalse($ctx['store']->fresh()->is_active);
    }

    public function test_the_users_row_is_soft_deleted_not_removed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/profile');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_a_deleted_account_can_no_longer_authenticate(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/profile');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertStatus(401);
    }

    public function test_existing_tokens_are_revoked_on_deletion(): void
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/profile');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_delete_profile_always_targets_the_authenticated_user(): void
    {
        // Endpoint ini tidak menerima id apa pun dari client -- tidak ada
        // parameter untuk dites di sini secara langsung, tapi ini
        // menegaskan kontraknya: menghapus akun A tidak boleh menyentuh B.
        $victim = User::factory()->create();
        $victim->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $victim->id]);

        $attacker = User::factory()->create();
        $attacker->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $attacker->id]);

        $this->actingAs($attacker, 'sanctum')->deleteJson('/api/profile');

        $this->assertSoftDeleted('users', ['id' => $attacker->id]);
        $this->assertDatabaseHas('users', ['id' => $victim->id, 'deleted_at' => null]);
    }

    public function test_a_deactivated_store_disappears_from_public_listing_and_lookup(): void
    {
        $ctx = $this->sellerWithHistory();
        $this->actingAs($ctx['sellerUser'], 'sanctum')->deleteJson('/api/profile');

        $listing = $this->getJson('/api/store')->json('data');
        $this->assertFalse(collect($listing)->contains('id', $ctx['store']->id));

        $this->getJson("/api/store/{$ctx['store']->id}")->assertStatus(404);
        $this->getJson("/api/store/username/{$ctx['store']->username}")->assertStatus(404);
    }

    public function test_products_from_a_deactivated_store_disappear_from_the_public_catalog(): void
    {
        $ctx = $this->sellerWithHistory();
        $this->actingAs($ctx['sellerUser'], 'sanctum')->deleteJson('/api/profile');

        $listing = $this->getJson('/api/product')->json('data');
        $this->assertFalse(collect($listing)->contains('id', $ctx['product']->id));
    }

    public function test_checkout_against_a_deactivated_store_is_rejected(): void
    {
        // Pertahanan lapis kedua: cart bisa saja sudah berisi produk ini
        // sebelum pemilik tokonya menghapus akun, sebelum katalog publik
        // sempat menyaringnya.
        $ctx = $this->sellerWithHistory();
        $this->actingAs($ctx['sellerUser'], 'sanctum')->deleteJson('/api/profile');

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $buyerUser->id]);

        $this->actingAs($buyerUser, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', [
                'address_id' => 1,
                'address' => 'Jl. Uji',
                'city' => 'Jakarta',
                'postal_code' => '12345',
                'shipping' => 'JNE',
                'shipping_type' => 'REG',
                'shipping_cost' => 15000,
                'products' => [
                    ['product_id' => $ctx['product']->id, 'qty' => 1],
                ],
            ])
            ->assertStatus(500);

        $this->assertSame(1, Transaction::count(), 'hanya transaksi seed awal, tidak ada transaksi baru');
    }
}
