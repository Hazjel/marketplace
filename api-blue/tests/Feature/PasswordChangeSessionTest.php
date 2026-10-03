<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * A password change has to end the sessions it is meant to protect against:
 * Sanctum tokens live 30 days, so without revocation a stolen session survives
 * the victim resetting their password.
 */
class PasswordChangeSessionTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'korban@blukios.com',
            'password' => bcrypt('Password123'),
        ]);
    }

    public function test_reset_password_revokes_every_token_and_clears_the_login_lock(): void
    {
        $user = $this->user();
        $user->createToken('laptop');
        $user->createToken('stolen');
        $lockKey = 'login_locked_'.md5('korban@blukios.com');
        Cache::put($lockKey, true, now()->addMinutes(15));

        $this->postJson('/api/password/reset', [
            'token' => Password::createToken($user),
            'email' => 'korban@blukios.com',
            'password' => 'BaruSekali123',
            'password_confirmation' => 'BaruSekali123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse(Cache::has($lockKey));

        $this->postJson('/api/login', [
            'email' => 'korban@blukios.com',
            'password' => 'BaruSekali123',
        ])->assertOk();
    }

    public function test_reset_password_uses_the_registration_password_rule(): void
    {
        $user = $this->user();

        $this->postJson('/api/password/reset', [
            'token' => Password::createToken($user),
            'email' => 'korban@blukios.com',
            'password' => 'semuakecil',
            'password_confirmation' => 'semuakecil',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_changing_password_in_profile_keeps_this_session_and_ends_the_others(): void
    {
        $user = $this->user();
        $current = $user->createToken('this-phone');
        $other = $user->createToken('other-laptop');

        $this->withToken($current->plainTextToken)->putJson('/api/profile', [
            'name' => $user->name,
            'current_password' => 'Password123',
            'password' => 'BaruSekali123',
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_profile_update_without_a_new_password_leaves_sessions_alone(): void
    {
        $user = $this->user();
        $current = $user->createToken('this-phone');
        $user->createToken('other-laptop');

        $this->withToken($current->plainTextToken)->putJson('/api/profile', [
            'name' => 'Nama Baru',
        ])->assertOk();

        $this->assertSame(2, $user->tokens()->count());
    }
}
