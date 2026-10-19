<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class P0EscrowRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyerUser;

    private Store $store;

    private StoreBalance $balance;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->seller = User::factory()->create();
        $this->seller->assignRole('store');
        $this->store = Store::factory()->create(['user_id' => $this->seller->id]);
        $this->balance = StoreBalance::create([
            'store_id' => $this->store->id,
            'balance' => 0,
            'pending_balance' => 0,
        ]);

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->assignRole('buyer');
        $this->buyer = Buyer::factory()->create(['user_id' => $this->buyerUser->id]);

        $category = ProductCategory::create([
            'name' => 'P0 Category',
            'slug' => 'p0-category',
            'description' => 'P0',
        ]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'price' => 100000,
            'stock' => 10,
        ]);
    }

    private function transaction(string $code, string $paymentStatus, string $deliveryStatus): Transaction
    {
        $transaction = Transaction::create([
            'code' => $code,
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'tax' => 0,
            'grand_total' => 115000,
            'payment_status' => $paymentStatus,
            'delivery_status' => $deliveryStatus,
            'admin_fee' => 10000,
            'seller_amount' => 100000,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return $transaction;
    }

    private function assertEscrowInvariant(): void
    {
        $pending = (float) StoreBalanceHistory::where('store_balance_id', $this->balance->id)
            ->where('reference_type', Transaction::class)
            ->where('type', StoreBalanceHistory::TYPE_PENDING_INCOME)
            ->sum('amount');
        $released = (float) StoreBalanceHistory::where('store_balance_id', $this->balance->id)
            ->where('reference_type', Transaction::class)
            ->where('type', StoreBalanceHistory::TYPE_RELEASED)
            ->sum('amount');
        $refunded = abs((float) StoreBalanceHistory::where('store_balance_id', $this->balance->id)
            ->where('reference_type', Transaction::class)
            ->where('type', StoreBalanceHistory::TYPE_REFUNDED)
            ->sum('amount'));

        $this->assertEqualsWithDelta(
            $pending - $released - $refunded,
            (float) $this->balance->fresh()->pending_balance,
            0.01,
            'pending_balance must equal the escrow ledger',
        );
    }

    public function test_unpaid_order_cannot_be_marked_delivering(): void
    {
        $transaction = $this->transaction('P0_ESCROW_UNPAID_STATUS', 'unpaid', 'pending');

        $this->actingAs($this->seller, 'sanctum')
            ->putJson("/api/transaction/{$transaction->id}", ['delivery_status' => 'delivering'])
            ->assertStatus(422);
        $this->assertEscrowInvariant();
    }

    public function test_unpaid_order_cannot_be_completed(): void
    {
        $transaction = $this->transaction('P0_ESCROW_UNPAID_COMPLETE', 'unpaid', 'delivering');

        Storage::fake('public');
        $this->actingAs($this->buyerUser, 'sanctum')
            ->post("/api/transaction/{$transaction->id}/complete", [
                'receiving_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422);
        $this->assertEscrowInvariant();
    }

    public function test_paid_order_without_its_pending_ledger_cannot_be_completed(): void
    {
        $otherTransaction = $this->transaction('P0_ESCROW_OTHER_PENDING', 'paid', 'delivering');
        $this->balance->update(['pending_balance' => 100000]);
        StoreBalanceHistory::create([
            'store_balance_id' => $this->balance->id,
            'type' => StoreBalanceHistory::TYPE_PENDING_INCOME,
            'reference_id' => $otherTransaction->id,
            'reference_type' => Transaction::class,
            'unique_ref' => 'pending_income:'.$otherTransaction->id,
            'amount' => 100000,
            'remarks' => 'other transaction escrow',
        ]);
        $transaction = $this->transaction('P0_ESCROW_MISSING_LEDGER', 'paid', 'delivering');

        Storage::fake('public');
        $this->actingAs($this->buyerUser, 'sanctum')
            ->post("/api/transaction/{$transaction->id}/complete", [
                'receiving_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertStatus(422);

        $this->assertSame('delivering', $transaction->fresh()->delivery_status);
        $this->assertSame(100000.0, (float) $this->balance->fresh()->pending_balance);
        $this->assertEscrowInvariant();
        $this->assertDatabaseMissing('store_balance_histories', [
            'reference_id' => $transaction->id,
            'type' => StoreBalanceHistory::TYPE_RELEASED,
        ]);
        $this->assertEscrowInvariant();
    }

    public function test_paid_order_with_matching_pending_ledger_can_be_completed(): void
    {
        $transaction = $this->transaction('P0_ESCROW_VALID', 'paid', 'delivering');
        $this->balance->update(['pending_balance' => 100000]);
        StoreBalanceHistory::create([
            'store_balance_id' => $this->balance->id,
            'type' => StoreBalanceHistory::TYPE_PENDING_INCOME,
            'reference_id' => $transaction->id,
            'reference_type' => Transaction::class,
            'unique_ref' => 'pending_income:'.$transaction->id,
            'amount' => 100000,
            'remarks' => 'test',
        ]);

        Storage::fake('public');
        $response = $this->actingAs($this->buyerUser, 'sanctum')
            ->post("/api/transaction/{$transaction->id}/complete", [
                'receiving_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);
        if ($response->status() === 200) {
            $response->assertOk();
        }

        $this->assertSame('completed', $transaction->fresh()->delivery_status);
        $this->assertSame(0.0, (float) $this->balance->fresh()->pending_balance);
        $this->assertSame(100000.0, (float) $this->balance->fresh()->balance);
        $this->assertEscrowInvariant();
    }
}
