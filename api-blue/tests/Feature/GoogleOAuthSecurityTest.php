<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleOAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

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
        $cookie = $this->nonceCookie($response);
        $nonce = $cookie->getValue();
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/api/auth/google', $cookie->getPath());
        $this->assertGreaterThan(500, $cookie->getMaxAge());
        $this->assertSame([
            'platform' => 'web',
            'nonce' => hash('sha256', $nonce),
        ], Cache::get('google.oauth.state.'.$capturedState));
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
        $nonce = 'replay-browser-nonce';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web', 'nonce' => hash('sha256', $nonce)], now()->addMinutes(10));
        $this->mockGoogleUser('replay@example.test');
        $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=abc')->assertRedirect();
        $before = DB::table('personal_access_tokens')->count();
        $usersBefore = User::count();
        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=abc')->assertRedirect();
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
        $nonce = 'secure-browser-nonce';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web', 'nonce' => hash('sha256', $nonce)], now()->addMinutes(10));
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getEmail')->andReturn('oauth@example.test');
        $googleUser->shouldReceive('getName')->andReturn('OAuth User');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=google-code');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $frontend = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $this->assertStringStartsWith($frontend.'/auth/google/callback?code=', $location);
        $this->assertStringNotContainsString('token=', $location);
        $this->assertNull(Cache::get('google.oauth.state.'.$state));

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $exchangeCode = $query['code'] ?? '';
        $exchangePayload = Cache::get('google.oauth.exchange.'.$exchangeCode);
        $this->assertSame([
            'user_id' => User::where('email', 'oauth@example.test')->value('id'),
            'nonce' => hash('sha256', $nonce),
        ], $exchangePayload);
        $exchange = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->postJson('/api/auth/google/exchange', ['code' => $exchangeCode]);
        $exchange->assertOk()->assertJsonStructure(['data' => ['token']]);
        $this->assertTrue(collect($exchange->headers->getCookies())->contains(
            fn ($cookie): bool => $cookie->getName() === 'google_oauth_nonce' && $cookie->getExpiresTime() < time(),
        ));
        $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->postJson('/api/auth/google/exchange', ['code' => $exchangeCode])
            ->assertStatus(422);
    }

    public function test_exchange_accepts_valid_code_once_and_rejects_invalid_or_replayed_codes(): void
    {
        $nonce = 'exchange-browser-nonce';
        Cache::put('google.oauth.exchange.valid-code', [
            'user_id' => User::factory()->create()->id,
            'nonce' => hash('sha256', $nonce),
        ], now()->addSeconds(60));
        $this->postJson('/api/auth/google/exchange', ['code' => 'valid-code'])->assertStatus(422);
        $this->withUnencryptedCookie('google_oauth_nonce', 'other-browser-nonce')
            ->postJson('/api/auth/google/exchange', ['code' => 'valid-code'])->assertStatus(422);
        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->postJson('/api/auth/google/exchange', ['code' => 'valid-code']);
        $response->assertOk()->assertJsonStructure(['data' => ['token']]);
        $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->postJson('/api/auth/google/exchange', ['code' => 'valid-code'])->assertStatus(422);
        $this->postJson('/api/auth/google/exchange', ['code' => 'bad-code'])->assertStatus(422);
    }

    public function test_mobile_callback_verifies_state_and_uses_mobile_deep_link(): void
    {
        $state = 'mobile-state';
        $nonce = 'mobile-browser-nonce';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'mobile', 'nonce' => hash('sha256', $nonce)], now()->addMinutes(10));
        $this->mockGoogleUser('mobile@example.test');

        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=abc');

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
        $nonce = 'error-browser-nonce';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web', 'nonce' => hash('sha256', $nonce)], now()->addMinutes(10));
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new RuntimeException('SECRET_PROVIDER_EXCEPTION'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=abc');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/login', $response->headers->get('Location'));
        $this->assertStringNotContainsString('SECRET_PROVIDER_EXCEPTION', $response->headers->get('Location'));
        $this->assertStringNotContainsString('SECRET_PROVIDER_EXCEPTION', $response->getContent());
    }

    public function test_callback_rejects_valid_state_without_nonce_or_with_another_browsers_nonce(): void
    {
        foreach (['web', 'mobile'] as $platform) {
            foreach ([null, 'other-browser-nonce'] as $cookie) {
                $state = 'cookie-state-'.$platform.'-'.($cookie === null ? 'missing' : 'other');
                $nonce = 'expected-browser-nonce';
                Cache::put('google.oauth.state.'.$state, [
                    'platform' => $platform,
                    'nonce' => hash('sha256', $nonce),
                ], now()->addMinutes(10));
                $request = $cookie === null
                    ? $this
                    : $this->withUnencryptedCookie('google_oauth_nonce', $cookie);
                $response = $request->get('/api/auth/google/callback?state='.$state.'&code=abc')->assertRedirect();
                $location = $response->headers->get('Location');
                $this->assertStringContainsString('/auth/login', $location);
                $this->assertStringNotContainsString('token=', $location);
                $this->assertStringNotContainsString('code=', $location);
                $this->assertNull(Cache::get('google.oauth.state.'.$state));
            }
        }
    }

    public function test_callback_retries_one_transient_connection_failure_then_succeeds(): void
    {
        $state = 'retry-state';
        $nonce = 'retry-browser-nonce';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web', 'nonce' => hash('sha256', $nonce)], now()->addMinutes(10));
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getEmail')->andReturn('retry@example.test');
        $googleUser->shouldReceive('getName')->andReturn('Retry User');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->twice()
            ->andThrow(new ConnectException('temporary DNS failure', new Request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo')))
            ->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=abc');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/google/callback?code=', $response->headers->get('Location'));
    }

    public function test_callback_retries_connection_failure_once_then_redirects_generically(): void
    {
        $state = 'retry-failed-state';
        $nonce = 'retry-failed-browser-nonce';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web', 'nonce' => hash('sha256', $nonce)], now()->addMinutes(10));
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->twice()
            ->andThrow(new ConnectException('temporary DNS failure', new Request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo')));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=abc');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/auth/login', $location);
        $this->assertStringNotContainsString('token=', $location);
        $this->assertStringNotContainsString('code=', $location);
    }

    public function test_callback_does_not_retry_non_connection_exception(): void
    {
        $state = 'no-retry-state';
        $nonce = 'no-retry-browser-nonce';
        Cache::put('google.oauth.state.'.$state, ['platform' => 'web', 'nonce' => hash('sha256', $nonce)], now()->addMinutes(10));
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('provider failed'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withUnencryptedCookie('google_oauth_nonce', $nonce)
            ->get('/api/auth/google/callback?state='.$state.'&code=abc');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/login', $response->headers->get('Location'));
    }

    public function test_redirect_on_seller_host_hops_to_callback_host_without_setting_nonce(): void
    {
        config(['services.google.redirect' => 'https://blukios.store/api/auth/google/callback']);
        Socialite::shouldReceive('driver')->with('google')->never();

        $response = $this->withServerVariables(['HTTP_HOST' => 'seller.blukios.store'])
            ->get('/api/auth/google/redirect?platform=web');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://blukios.store/api/auth/google/redirect?platform=web', $location);
        $this->assertEmpty($response->headers->getCookies());
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

    private function nonceCookie($response)
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'google_oauth_nonce') {
                return $cookie;
            }
        }

        $this->fail('Google OAuth nonce cookie was not set.');
    }
}
