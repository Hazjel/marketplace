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
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * UserController::destroy() (admin) dan AuthController::deleteAccount()
 * (self-service) dulu punya dua semantics untuk satu operasi: keduanya
 * berujung ke $user->delete(), tapi hanya jalur self-service yang ikut
 * menonaktifkan toko. Admin yang menghapus seller lewat endpoint ini
 * meninggalkan stores.is_active tetap true -- soft-delete-nya aman dari
 * cascade finansial, tapi tokonya tetap tampil dan bisa dibeli meski
 * pemiliknya sudah tidak punya akun untuk mengurusnya.
 *
 * AccountDeletionService sekarang jadi satu-satunya tempat operasi ini
 * didefinisikan, dipakai kedua jalur.
 */
class AdminAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
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

        $category = ProductCategory::create([
            'name' => 'General', 'slug' => 'general-admin-deletion', 'description' => 'General',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Barang Uji Admin Hapus',
            'slug' => 'barang-uji-admin-hapus',
            'description' => 'Deskripsi',
            'condition' => 'new',
            'price' => 100000,
            'weight' => 1000,
            'stock' => 10,
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = Buyer::factory()->create(['user_id' => $buyerUser->id]);

        $transaction = Transaction::create([
            'code' => 'ADMIN_DEL_001',
            'buyer_id' => $buyer->id,
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

        return compact('sellerUser', 'store', 'storeBalance', 'product', 'transaction');
    }

    public function test_admin_deleting_a_seller_also_deactivates_the_store(): void
    {
        $ctx = $this->sellerWithHistory();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/user/{$ctx['sellerUser']->id}")
            ->assertStatus(200);

        $this->assertFalse($ctx['store']->fresh()->is_active, 'admin delete harus ikut menonaktifkan toko, sama seperti self-delete');
    }

    public function test_admin_deletion_also_preserves_financial_history(): void
    {
        $ctx = $this->sellerWithHistory();

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/user/{$ctx['sellerUser']->id}");

        $this->assertDatabaseHas('transactions', ['id' => $ctx['transaction']->id]);
        $this->assertDatabaseHas('products', ['id' => $ctx['product']->id]);
        $this->assertDatabaseHas('store_balances', ['id' => $ctx['storeBalance']->id]);
    }

    public function test_admin_deletion_soft_deletes_not_hard_deletes(): void
    {
        $ctx = $this->sellerWithHistory();

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/user/{$ctx['sellerUser']->id}");

        $this->assertSoftDeleted('users', ['id' => $ctx['sellerUser']->id]);
    }

    public function test_admin_deletion_revokes_the_users_tokens(): void
    {
        $ctx = $this->sellerWithHistory();
        $ctx['sellerUser']->createToken('sisa-sesi');

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/user/{$ctx['sellerUser']->id}");

        $this->assertSame(0, $ctx['sellerUser']->tokens()->count());
    }

    public function test_deactivated_store_from_admin_deletion_disappears_publicly(): void
    {
        $ctx = $this->sellerWithHistory();

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/user/{$ctx['sellerUser']->id}");

        $this->getJson("/api/store/{$ctx['store']->id}")->assertStatus(404);

        $listing = $this->getJson('/api/product')->json('data');
        $this->assertFalse(collect($listing)->contains('id', $ctx['product']->id));
    }
}
