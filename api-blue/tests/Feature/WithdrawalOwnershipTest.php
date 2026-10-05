<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\User;
use App\Models\Withdrawal;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** A seller may only withdraw from their own store's balance. */
class WithdrawalOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function sellerWithBalance(string $username): array
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');

        $store = Store::create([
            'user_id' => $seller->id,
            'name' => 'Toko '.$username,
            'username' => $username,
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);

        $balance = StoreBalance::create([
            'store_id' => $store->id,
            'balance' => 500000,
            'pending_balance' => 0,
        ]);

        return [$seller, $balance];
    }

    private function withdraw(User $seller, StoreBalance $balance)
    {
        return $this->actingAs($seller)->postJson('/api/withdrawal', [
            'store_balance_id' => $balance->id,
            'amount' => 100000,
            'bank_name' => 'bca',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Penjual',
        ]);
    }

    public function test_seller_cannot_withdraw_another_stores_balance(): void
    {
        [$attacker] = $this->sellerWithBalance('penyerang');
        [, $victimBalance] = $this->sellerWithBalance('korban');

        $this->withdraw($attacker, $victimBalance)
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_balance_id');

        $this->assertSame(0, Withdrawal::count());
        $this->assertEquals(500000, $victimBalance->fresh()->balance);
    }

    public function test_seller_can_withdraw_own_balance(): void
    {
        [$seller, $balance] = $this->sellerWithBalance('pemilik');

        $this->withdraw($seller, $balance)->assertCreated();

        $this->assertEquals(400000, $balance->fresh()->balance);
    }
}
