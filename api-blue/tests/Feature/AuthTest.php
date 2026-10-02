<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $payload = [
            'name' => 'Tester Blue',
            'username' => 'testerblue',
            'email' => 'test@blukios.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'phone_number' => '081234567890',
            'role' => 'buyer',
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg'),
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'id', 'name', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'test@blukios.com']);
    }

    public function test_https_signed_verification_link_works_behind_the_tls_proxy(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        URL::forceScheme('https');
        $signed = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        // Cloudflare terminates TLS: PHP receives plain http plus X-Forwarded-Proto.
        $this->withHeader('X-Forwarded-Proto', 'https')
            ->get(str_replace('https://', 'http://', $signed))
            ->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_can_login()
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'email' => 'login@blukios.com',
            'password' => bcrypt('Password123'),
        ]);
        $user->assignRole('buyer');

        $response = $this->postJson('/api/login', [
            'email' => 'login@blukios.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token']]);
    }
}
