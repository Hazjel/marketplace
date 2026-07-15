<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_store()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $payload = [
            'name' => 'Toko Bagus',
            'phone' => '081299998888',
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345',
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/register-store', $payload);

        $response->assertStatus(201);
        // Username digenerate dari nama toko + suffix acak (mis. toko-bagus-Y84x8)
        $this->assertDatabaseHas('stores', ['name' => 'Toko Bagus']);

        // User should now have 'store' role
        $this->assertTrue($user->fresh()->hasRole('store'));
    }
}
