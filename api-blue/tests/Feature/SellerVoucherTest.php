<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SellerVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function makeSeller(): array
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id,
            'name' => 'Seller Store',
            'username' => 'sellerstore-'.Str::random(6),
            'logo' => 'default.png',
            'about' => 'About',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Seller',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        return [$seller, $store];
    }

    public function test_seller_can_create_voucher_scoped_to_own_store()
    {
        [$seller, $store] = $this->makeSeller();

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/my-store/vouchers', [
            'code' => 'toko10',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 5000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'TOKO10') // uppercased
            ->assertJsonPath('data.store_id', $store->id);

        $this->assertDatabaseHas('vouchers', ['code' => 'TOKO10', 'store_id' => $store->id]);
    }

    public function test_store_id_cannot_be_spoofed_to_platform_wide()
    {
        [$seller, $store] = $this->makeSeller();

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/my-store/vouchers', [
            'code' => 'HACK1',
            'type' => 'fixed',
            'value' => 999999,
            'store_id' => null,
        ]);

        $response->assertStatus(201);
        // Even though the payload didn't (and can't) set store_id, the
        // created voucher must always be scoped to the caller's store —
        // never platform-wide (store_id null).
        $this->assertDatabaseHas('vouchers', ['code' => 'HACK1', 'store_id' => $store->id]);
    }

    public function test_seller_without_a_store_is_rejected()
    {
        $buyerOnly = User::factory()->create();
        $buyerOnly->assignRole('buyer');

        $response = $this->actingAs($buyerOnly, 'sanctum')->postJson('/api/my-store/vouchers', [
            'code' => 'NOPE',
            'type' => 'fixed',
            'value' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_seller_cannot_update_another_stores_voucher()
    {
        [, $storeA] = $this->makeSeller();
        [$sellerB] = $this->makeSeller();

        $voucher = Voucher::create([
            'code' => 'STOREA10',
            'store_id' => $storeA->id,
            'type' => 'fixed',
            'value' => 1000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($sellerB, 'sanctum')->putJson("/api/my-store/vouchers/{$voucher->id}", [
            'code' => 'STOLEN',
            'type' => 'fixed',
            'value' => 1000,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'code' => 'STOREA10']);
    }

    public function test_duplicate_code_is_rejected()
    {
        [$seller, $store] = $this->makeSeller();
        Voucher::create([
            'code' => 'DUPE10',
            'store_id' => $store->id,
            'type' => 'fixed',
            'value' => 1000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/my-store/vouchers', [
            'code' => 'dupe10',
            'type' => 'fixed',
            'value' => 2000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_delete_succeeds_when_voucher_never_used()
    {
        [$seller, $store] = $this->makeSeller();
        $voucher = Voucher::create([
            'code' => 'UNUSED',
            'store_id' => $store->id,
            'type' => 'fixed',
            'value' => 1000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($seller, 'sanctum')->deleteJson("/api/my-store/vouchers/{$voucher->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }

    public function test_delete_is_blocked_once_voucher_has_redemption_history()
    {
        [$seller, $store] = $this->makeSeller();
        $voucher = Voucher::create([
            'code' => 'USED10',
            'store_id' => $store->id,
            'type' => 'fixed',
            'value' => 1000,
            'is_active' => true,
        ]);
        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = $buyerUser->buyer()->create(['phone_number' => '08987654321', 'city' => 'Bandung', 'address' => 'Jl. Buyer']);
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'buyer_id' => $buyer->id,
            'redeemed_at' => now(),
        ]);

        $response = $this->actingAs($seller, 'sanctum')->deleteJson("/api/my-store/vouchers/{$voucher->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
    }

    public function test_index_only_lists_own_stores_vouchers()
    {
        [$sellerA, $storeA] = $this->makeSeller();
        [, $storeB] = $this->makeSeller();
        Voucher::create(['code' => 'MINE', 'store_id' => $storeA->id, 'type' => 'fixed', 'value' => 1000, 'is_active' => true]);
        Voucher::create(['code' => 'THEIRS', 'store_id' => $storeB->id, 'type' => 'fixed', 'value' => 1000, 'is_active' => true]);

        $response = $this->actingAs($sellerA, 'sanctum')->getJson('/api/my-store/vouchers');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('MINE'));
        $this->assertFalse($codes->contains('THEIRS'));
    }
}
