<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class P0TransactionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_user_without_store_gets_empty_store_lists(): void
    {
        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = Buyer::factory()->create(['user_id' => $buyerUser->id]);

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id]);
        $transaction = Transaction::create([
            'code' => 'P0_SCOPE_OTHER_STORE',
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 0,
            'grand_total' => 10000,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/transaction?mode=store')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/transaction/all/paginated?mode=store&row_per_page=10')
            ->assertOk()
            ->assertJsonPath('data.data', []);

        $this->assertNotEmpty($transaction->id);
    }

    public function test_invalid_transaction_mode_is_unprocessable(): void
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/transaction?mode=invalid')
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/transaction/all/paginated?mode=invalid&row_per_page=10')
            ->assertStatus(422);
    }

    public function test_index_and_paginated_lists_are_scoped_for_buyer_seller_and_admin(): void
    {
        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = Buyer::factory()->create(['user_id' => $buyerUser->id]);
        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('store');
        $store = Store::factory()->create(['user_id' => $sellerUser->id]);
        $otherBuyer = Buyer::factory()->create(['user_id' => User::factory()->create()->id]);

        $own = Transaction::create([
            'code' => 'P0_SCOPE_OWN',
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'address_id' => 1, 'address' => 'A', 'city' => 'Jakarta',
            'postal_code' => '12345', 'shipping' => 'JNE', 'shipping_type' => 'REG',
            'shipping_cost' => 10000, 'tax' => 0, 'grand_total' => 10000, 'payment_status' => 'unpaid',
        ]);
        $other = Transaction::create([
            'code' => 'P0_SCOPE_OTHER',
            'buyer_id' => $otherBuyer->id,
            'store_id' => $store->id,
            'address_id' => 1, 'address' => 'B', 'city' => 'Jakarta',
            'postal_code' => '12345', 'shipping' => 'JNE', 'shipping_type' => 'REG',
            'shipping_cost' => 10000, 'tax' => 0, 'grand_total' => 10000, 'payment_status' => 'unpaid',
        ]);

        $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/transaction?mode=buyer')
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonMissing(['id' => $other->id]);
        $this->actingAs($sellerUser, 'sanctum')
            ->getJson('/api/transaction?mode=store')
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonFragment(['id' => $other->id]);
        $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/transaction/all/paginated?mode=buyer&row_per_page=10')
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonMissing(['id' => $other->id]);
        $this->actingAs($sellerUser, 'sanctum')
            ->getJson('/api/transaction/all/paginated?mode=store&row_per_page=10')
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonFragment(['id' => $other->id]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/transaction?mode=store')
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonFragment(['id' => $other->id]);
    }

    public function test_user_cannot_view_another_buyers_transaction_detail(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('buyer');
        $ownerBuyer = Buyer::factory()->create(['user_id' => $owner->id]);
        $other = User::factory()->create();
        $other->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $other->id]);
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id]);
        $transaction = Transaction::create([
            'code' => 'P0_SCOPE_IDOR', 'buyer_id' => $ownerBuyer->id, 'store_id' => $store->id,
            'address_id' => 1, 'address' => 'A', 'city' => 'Jakarta', 'postal_code' => '12345',
            'shipping' => 'JNE', 'shipping_type' => 'REG', 'shipping_cost' => 10000,
            'tax' => 0, 'grand_total' => 10000, 'payment_status' => 'unpaid',
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/transaction/{$transaction->id}")
            ->assertStatus(403);
    }
}
