<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_registers_a_new_token()
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/device-token', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm-token-abc',
            'user_id' => $user->id,
            'platform' => 'android',
        ]);
    }

    public function test_reregistering_the_same_token_upserts_instead_of_duplicating()
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');

        $this->actingAs($user, 'sanctum')->postJson('/api/device-token', ['token' => 'fcm-token-abc']);
        $this->actingAs($user, 'sanctum')->postJson('/api/device-token', ['token' => 'fcm-token-abc', 'platform' => 'android']);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'fcm-token-abc', 'platform' => 'android']);
    }

    public function test_same_device_token_reassigns_to_a_different_user_on_re_registration()
    {
        $userA = User::factory()->create();
        $userA->assignRole('buyer');
        $userB = User::factory()->create();
        $userB->assignRole('buyer');

        $this->actingAs($userA, 'sanctum')->postJson('/api/device-token', ['token' => 'shared-device']);
        $this->actingAs($userB, 'sanctum')->postJson('/api/device-token', ['token' => 'shared-device']);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'shared-device', 'user_id' => $userB->id]);
    }

    public function test_cannot_unregister_another_users_token()
    {
        $owner = User::factory()->create();
        $owner->assignRole('buyer');
        $attacker = User::factory()->create();
        $attacker->assignRole('buyer');

        $this->actingAs($owner, 'sanctum')->postJson('/api/device-token', ['token' => 'owners-token']);

        $this->actingAs($attacker, 'sanctum')->deleteJson('/api/device-token', ['token' => 'owners-token']);

        $this->assertDatabaseHas('device_tokens', ['token' => 'owners-token', 'user_id' => $owner->id]);
    }

    public function test_owner_can_unregister_their_own_token()
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');
        $this->actingAs($user, 'sanctum')->postJson('/api/device-token', ['token' => 'my-token']);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/device-token', ['token' => 'my-token']);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'my-token']);
    }
}
