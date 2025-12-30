<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use Illuminate\Http\UploadedFile;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $payload = [
            'name' => 'Tester Blue',
            'username' => 'testerblue',
            'email' => 'test@bluee.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '081234567890',
            'role' => 'buyer',
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg')
        ];

        $response = $this->postJson('/api/register', $payload);
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['token', 'id', 'name', 'email']]);
        
        $this->assertDatabaseHas('users', ['email' => 'test@bluee.com']);
    }

    public function test_user_can_login()
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create([
            'email' => 'login@bluee.com',
            'password' => bcrypt('password123')
        ]);
        $user->guard_name = 'sanctum';
        $user->assignRole('buyer');

        $response = $this->postJson('/api/login', [
            'email' => 'login@bluee.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['token']]);
    }
}
