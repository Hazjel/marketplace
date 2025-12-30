<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use Illuminate\Http\UploadedFile;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_store()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create();
        $user->guard_name = 'sanctum';
        $payload = [
            'name' => 'Toko Bagus',
            'phone' => '081299998888',
            'city' => 'Jakarta',
            'address' => 'Jl Sudirman',
            'postal_code' => '12345'
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/register-store', $payload);
        $response->dump();

        $response->assertStatus(201);
        $this->assertDatabaseHas('stores', ['username' => 'tokobagus']);
        
        // User should now have 'store' role
        $this->assertTrue($user->fresh()->hasRole('store'));
    }
}
