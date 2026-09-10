<?php

namespace Tests\Feature;

use App\Interfaces\EscrowRepositoryInterface;
use App\Models\Buyer;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * B3.2d: the platform admin fee is computed once, at credit time, with
 * Money->percentage(admin_fee_basis_points) (HALF_UP) on net sales
 * (grand total minus shipping). It is then locked in transactions.admin_fee
 * and reused by release/refund — never recomputed. The old duplicate
 * implementation in App\Services\TransactionService was dead code and is
 * removed.
 */
class AdminFeeMoneyTest extends TestCase
{
    use RefreshDatabase;

    private function transaction(int $grandTotal, int $shippingCost): Transaction
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // TransactionFactory::afterCreating builds products, which need a category.
        ProductCategory::firstOrCreate(
            ['slug' => 'admin-fee'],
            ['name' => 'Admin Fee', 'description' => 'fixture']
        );

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id]);
        StoreBalance::create(['store_id' => $store->id, 'balance' => 0, 'pending_balance' => 0]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = Buyer::create([
            'user_id' => $buyerUser->id, 'phone_number' => '08', 'city' => 'B', 'address' => 'A',
        ]);

        $transaction = Transaction::factory()->create([
            'store_id' => $store->id,
            'buyer_id' => $buyer->id,
            'payment_status' => 'paid',
        ]);

        // TransactionFactory::afterCreating recomputes grand_total from the
        // random details it generates; pin the money fields afterwards.
        $transaction->forceFill([
            'shipping_cost' => $shippingCost,
            'grand_total' => $grandTotal,
            'tax' => 0,
            'admin_fee' => 0,
        ])->save();

        return $transaction->fresh();
    }

    public function test_admin_fee_is_ten_percent_half_up_of_net_sales_locked_at_credit(): void
    {
        // net sales = 126_005 - 15_000 = 111_005 ; 10% = 11_100.5 -> HALF_UP -> 11_101
        $transaction = $this->transaction(grandTotal: 126_005, shippingCost: 15_000);

        app(EscrowRepositoryInterface::class)->credit($transaction);

        $this->assertSame('11101', (string) (int) $transaction->fresh()->admin_fee);

        $store = $transaction->store;
        // seller amount = 111_005 - 11_101 = 99_904 sits in pending
        $this->assertSame(99_904, (int) $store->storeBalance->fresh()->pending_balance);
    }

    public function test_release_reuses_the_locked_fee_and_does_not_recompute(): void
    {
        $transaction = $this->transaction(grandTotal: 126_005, shippingCost: 15_000);
        $escrow = app(EscrowRepositoryInterface::class);

        $escrow->credit($transaction);
        $lockedFee = (int) $transaction->fresh()->admin_fee;

        // change the fee config after credit — release must not pick it up
        config(['marketplace.admin_fee_basis_points' => 2500]);

        $escrow->release($transaction->fresh());

        $store = $transaction->store;
        $this->assertSame($lockedFee, (int) $transaction->fresh()->admin_fee);
        $this->assertSame(99_904, (int) $store->storeBalance->fresh()->balance);
        $this->assertSame(0, (int) $store->storeBalance->fresh()->pending_balance);
    }
}
