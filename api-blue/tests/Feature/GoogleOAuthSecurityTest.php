<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_uses_random_cached_state_bound_to_platform(): void
    {
        $capturedState = null;
        $provider = Mockery::mock();
        $provider->shouldReceive('redirectUrl')->withAnyArgs()->andReturnSelf();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->withArgs(function (array $params) use (&$capturedState): bool {
            $capturedState = $params['state'] ?? null;

            return is_string($capturedState)
                && strlen($capturedState) >= 32
                && $capturedState !== http_build_query(['platform' => 'web']);
        })->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.test/oauth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/api/auth/google/redirect?platform=web');

        $response->assertRedirect('https://accounts.google.test/oauth');
        $this->assertNotNull($capturedState);
        $this->assertSame(['platform' => 'web'], Cache::get('google.oauth.state.'.$capturedState));
    }

    public function test_callback_rejects_missing_unknown_and_replayed_state_without_creating_user(): void
    {
        $this->mockGoogleUser('invalid-state@example.test');
        foreach ([
            '/api/auth/google/callback',
            '/api/auth/google/callback?state=unknown-state&code=abc',
        ] as $url) {
            $before = DB::table('personal_access_tokens')->count();
            $response = $this->get($url)->assertRedirect();
            $location = $response->headers->get('Location');
            $this->assertStringStartsWith(env('FRONTEND_URL', 'http://localhost:5173').'/auth/login', $location);
            $this->assertStringNotContainsString('token=', $location);
            $this->assertStringNotContainsString('code=', $location);
            $this->assertSame($before, DB::table('personal_access_tokens')->count());
        }

        $state = 'replay-state';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web'], now()->addMinutes(10));
        $this->mockGoogleUser('replay@example.test');
        $this->get('/api/auth/google/callback?state='.$state.'&code=abc')->assertRedirect();
        $before = DB::table('personal_access_tokens')->count();
        $usersBefore = User::count();
        $response = $this->get('/api/auth/google/callback?state='.$state.'&code=abc')->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(env('FRONTEND_URL', 'http://localhost:5173').'/auth/login', $location);
        $this->assertStringNotContainsString('token=', $location);
        $this->assertStringNotContainsString('code=', $location);
        $this->assertSame($before, DB::table('personal_access_tokens')->count());
        $this->assertSame($usersBefore, User::count());
    }

    public function test_valid_web_callback_returns_one_time_exchange_code_not_sanctum_token(): void
    {
        $state = 'secure-state';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web'], now()->addMinutes(10));
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getEmail')->andReturn('oauth@example.test');
        $googleUser->shouldReceive('getName')->andReturn('OAuth User');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/api/auth/google/callback?state='.$state.'&code=google-code');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $frontend = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $this->assertStringStartsWith($frontend.'/auth/google/callback?code=', $location);
        $this->assertStringNotContainsString('token=', $location);
        $this->assertNull(Cache::get('google.oauth.state.'.$state));

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $exchange = $this->postJson('/api/auth/google/exchange', ['code' => $query['code'] ?? '']);
        $exchange->assertOk()->assertJsonStructure(['data' => ['token']]);
        $this->postJson('/api/auth/google/exchange', ['code' => $query['code'] ?? ''])
            ->assertStatus(422);
    }

    public function test_exchange_accepts_valid_code_once_and_rejects_invalid_or_replayed_codes(): void
    {
        Cache::put('google.oauth.exchange.valid-code', ['user_id' => User::factory()->create()->id], now()->addSeconds(60));
        $response = $this->postJson('/api/auth/google/exchange', ['code' => 'valid-code']);
        $response->assertOk()->assertJsonStructure(['data' => ['token']]);
        $this->postJson('/api/auth/google/exchange', ['code' => 'valid-code'])->assertStatus(422);
        $this->postJson('/api/auth/google/exchange', ['code' => 'bad-code'])->assertStatus(422);
    }

    public function test_mobile_callback_verifies_state_and_uses_mobile_deep_link(): void
    {
        $state = 'mobile-state';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'mobile'], now()->addMinutes(10));
        $this->mockGoogleUser('mobile@example.test');

        $response = $this->get('/api/auth/google/callback?state='.$state.'&code=abc');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('mobileblue://callback?', $location);
        $this->assertStringContainsString('token=', $location);
        $this->assertStringContainsString('username=', $location);
        $this->assertNull(Cache::get('google.oauth.state.'.$state));
    }

    public function test_callback_errors_are_generic_and_do_not_echo_exception_text(): void
    {
        $state = 'error-state';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web'], now()->addMinutes(10));
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('SECRET_PROVIDER_EXCEPTION'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/api/auth/google/callback?state='.$state.'&code=abc');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/login', $response->headers->get('Location'));
        $this->assertStringNotContainsString('SECRET_PROVIDER_EXCEPTION', $response->headers->get('Location'));
        $this->assertStringNotContainsString('SECRET_PROVIDER_EXCEPTION', $response->getContent());
    }

    private function mockGoogleUser(string $email): void
    {
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn('OAuth User');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
