<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class P0StoreBalanceHistoryScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_seller_cannot_see_another_stores_history_but_admin_can(): void
    {
        $sellerA = User::factory()->create();
        $sellerA->assignRole('store');
        $storeA = Store::factory()->create(['user_id' => $sellerA->id]);
        $balanceA = StoreBalance::create(['store_id' => $storeA->id, 'balance' => 0, 'pending_balance' => 0]);

        $sellerB = User::factory()->create();
        $sellerB->assignRole('store');
        $storeB = Store::factory()->create(['user_id' => $sellerB->id]);
        $balanceB = StoreBalance::create(['store_id' => $storeB->id, 'balance' => 0, 'pending_balance' => 0]);

        $historyA = StoreBalanceHistory::create([
            'store_balance_id' => $balanceA->id, 'type' => 'income', 'reference_id' => (string) Str::uuid(),
            'reference_type' => StoreBalance::class, 'amount' => 100, 'remarks' => 'A',
        ]);
        $historyB = StoreBalanceHistory::create([
            'store_balance_id' => $balanceB->id, 'type' => 'income', 'reference_id' => (string) Str::uuid(),
            'reference_type' => StoreBalance::class, 'amount' => 200, 'remarks' => 'B',
        ]);

        $index = $this->actingAs($sellerA, 'sanctum')
            ->getJson('/api/store-balance-history?limit=100')
            ->assertOk();
        $this->assertSame([$historyA->id], collect($index->json('data'))->pluck('id')->all());

        $paginated = $this->actingAs($sellerA, 'sanctum')
            ->getJson('/api/store-balance-history/all/paginated?row_per_page=100')
            ->assertOk();
        $this->assertSame([$historyA->id], collect($paginated->json('data.data'))->pluck('id')->all());

        $this->actingAs($sellerA, 'sanctum')
            ->getJson("/api/store-balance-history/{$historyB->id}")
            ->assertStatus(404);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $adminIndex = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/store-balance-history?limit=100')
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$historyA->id, $historyB->id],
            collect($adminIndex->json('data'))->pluck('id')->all(),
        );
    }
}
