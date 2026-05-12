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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCompleteTransactionTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private StoreBalance $storeBalance;
    private Buyer $buyer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Setup Store
        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('store');

        $this->store = Store::create([
            'user_id' => $sellerUser->id,
            'name' => 'AutoComplete Store',
            'username' => 'autocompletestore',
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);

        $this->storeBalance = StoreBalance::create([
            'store_id' => $this->store->id,
            'balance' => 0,
            'pending_balance' => 0,
        ]);

        // Setup Product
        $category = ProductCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'description' => 'General',
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'name' => 'Auto Complete Product',
            'slug' => 'auto-complete-product',
            'description' => 'Test',
            'price' => 200000,
            'stock' => 10,
            'weight' => 1,
            'condition' => 'new',
        ]);

        // Setup Buyer
        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');

        $this->buyer = Buyer::create([
            'user_id' => $buyerUser->id,
            'phone_number' => '08987654321',
        ]);
    }

    public function test_auto_complete_releases_escrow_for_old_delivering_transactions()
    {
        // Create transaction that's been "delivering" for 8 days
        $transaction = Transaction::create([
            'code' => 'BLUE_AUTO_001',
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 22000,
            'grand_total' => 232000,
            'payment_status' => 'paid',
            'delivery_status' => 'delivering',
            'admin_fee' => 22200,
        ]);

        // Backdate updated_at to 8 days ago (use DB::table to bypass model events)
        \Illuminate\Support\Facades\DB::table('transactions')
            ->where('id', $transaction->id)
            ->update(['updated_at' => now()->subDays(8)->toDateTimeString()]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'subtotal' => 200000,
        ]);

        // Setup pending_balance
        $netSales = 232000 - 10000;
        $adminFee = $netSales * 0.10;
        $sellerAmount = $netSales - $adminFee;
        $this->storeBalance->update(['pending_balance' => $sellerAmount]);

        // Run the command
        $this->artisan('transaction:auto-complete')
            ->assertExitCode(0);

        // Verify: transaction marked as completed
        $transaction->refresh();
        $this->assertEquals('completed', $transaction->delivery_status);

        // Verify: escrow released
        $this->storeBalance->refresh();
        $this->assertEquals($sellerAmount, (float) $this->storeBalance->balance);
        $this->assertEquals(0, (float) $this->storeBalance->pending_balance);

        // Verify: history created
        $this->assertDatabaseHas('store_balance_histories', [
            'store_balance_id' => $this->storeBalance->id,
            'type' => 'released',
            'reference_id' => $transaction->id,
        ]);
    }

    public function test_auto_complete_does_not_affect_recent_transactions()
    {
        // Create transaction that's been "delivering" for only 3 days
        $transaction = Transaction::create([
            'code' => 'BLUE_AUTO_002',
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 22000,
            'grand_total' => 232000,
            'payment_status' => 'paid',
            'delivery_status' => 'delivering',
        ]);

        // Only 3 days ago - should NOT be auto-completed
        \Illuminate\Support\Facades\DB::table('transactions')
            ->where('id', $transaction->id)
            ->update(['updated_at' => now()->subDays(3)->toDateTimeString()]);

        $this->storeBalance->update(['pending_balance' => 100000]);

        // Run the command
        $this->artisan('transaction:auto-complete')
            ->assertExitCode(0);

        // Verify: transaction NOT completed
        $transaction->refresh();
        $this->assertEquals('delivering', $transaction->delivery_status);

        // Verify: balance unchanged
        $this->storeBalance->refresh();
        $this->assertEquals(0, (float) $this->storeBalance->balance);
        $this->assertEquals(100000, (float) $this->storeBalance->pending_balance);
    }

    public function test_auto_complete_ignores_non_delivering_transactions()
    {
        // Transaction that's paid but not yet "delivering" (still pending)
        $transaction = Transaction::create([
            'code' => 'BLUE_AUTO_003',
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 22000,
            'grand_total' => 232000,
            'payment_status' => 'paid',
            'delivery_status' => 'pending', // Not delivering
        ]);

        \Illuminate\Support\Facades\DB::table('transactions')
            ->where('id', $transaction->id)
            ->update(['updated_at' => now()->subDays(10)->toDateTimeString()]);

        $this->storeBalance->update(['pending_balance' => 100000]);

        $this->artisan('transaction:auto-complete')
            ->assertExitCode(0);

        // Should NOT be auto-completed
        $transaction->refresh();
        $this->assertEquals('pending', $transaction->delivery_status);
    }
}
