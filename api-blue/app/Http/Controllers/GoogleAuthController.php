<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private const NONCE_COOKIE = 'google_oauth_nonce';

    private const NONCE_PATH = '/api/auth/google';

    public function redirect(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'sometimes|string|in:web,mobile',
        ]);

        try {
            $callback = parse_url((string) config('services.google.redirect'));
            if (isset($callback['host']) && strcasecmp($request->getHost(), $callback['host']) !== 0) {
                if (! in_array($callback['scheme'] ?? null, ['http', 'https'], true)) {
                    return $this->loginError();
                }

                $origin = $callback['scheme'].'://'.$callback['host'].(isset($callback['port']) ? ':'.$callback['port'] : '');
                $query = http_build_query($validated);

                return redirect($origin.self::NONCE_PATH.'/redirect'.($query !== '' ? '?'.$query : ''));
            }

            $state = Str::random(64);
            $nonce = Str::random(64);
            Cache::put('google.oauth.state.'.$state, [
                'platform' => $validated['platform'] ?? 'web',
                'nonce' => hash('sha256', $nonce),
            ], now()->addMinutes(10));

            $response = Socialite::driver('google')
                ->redirectUrl(config('services.google.redirect'))
                ->stateless()
                ->with(['prompt' => 'select_account', 'state' => $state])
                ->redirect();
            $response->headers->setCookie(cookie(
                name: self::NONCE_COOKIE,
                value: $nonce,
                minutes: 10,
                path: self::NONCE_PATH,
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ));

            return $response;
        } catch (Throwable $e) {
            Log::error('GoogleAuth: Redirect failed', ['error' => $e->getMessage()]);

            return $this->loginError();
        }
    }

    public function callback(Request $request)
    {
        try {
            $state = $request->query('state');
            if (! is_string($state) || $state === '' || strlen($state) > 128) {
                return $this->loginError();
            }

            $context = $this->pullOnce('google.oauth.state.'.$state);
            if (! is_array($context) || ! in_array($context['platform'] ?? null, ['web', 'mobile'], true) || ! $this->matchesNonce($request, $context)) {
                return $this->loginError();
            }

            $provider = Socialite::driver('google')->stateless();
            try {
                $googleUser = $provider->user();
            } catch (ConnectException) {
                $googleUser = $provider->user();
            }

            $user = DB::transaction(function () use ($googleUser) {
                $user = User::where('email', $googleUser->getEmail())->first();

                if (! $user) {
                    $user = new User;
                    $user->name = $googleUser->getName();
                    $user->email = $googleUser->getEmail();
                    $user->email_verified_at = now();
                    $user->password = bcrypt(Str::random(16));
                    $user->profile_picture = $googleUser->getAvatar();

                    $slug = Str::slug($user->name);
                    $count = 1;
                    while (User::where('username', $slug)->exists()) {
                        $slug = Str::slug($user->name).'-'.$count;
                        $count++;
                    }
                    $user->username = $slug;
                    $user->save();
                    $user->assignRole('buyer');
                    $user->buyer()->create(['phone_number' => null]);
                }

                return $user;
            });

            if ($context['platform'] === 'mobile') {
                $token = $user->createToken('auth_token')->plainTextToken;

                return redirect('mobileblue://callback?'.http_build_query(['token' => $token, 'username' => $user->username]))
                    ->withoutCookie(self::NONCE_COOKIE, self::NONCE_PATH);
            }

            $code = Str::random(64);
            Cache::put('google.oauth.exchange.'.$code, [
                'user_id' => $user->getKey(),
                'nonce' => $context['nonce'],
            ], now()->addSeconds(60));

            return redirect(rtrim(config('app.frontend_url'), '/').'/auth/google/callback?code='.$code);
        } catch (Throwable $e) {
            Log::error('GoogleAuth: Callback failed', ['error' => $e->getMessage()]);

            return $this->loginError();
        }
    }

    public function exchange(Request $request)
    {
        $validated = $request->validate(['code' => 'required|string|max:128']);

        try {
            $context = $this->pullOnce('google.oauth.exchange.'.$validated['code'], $request);
            $user = is_array($context) && isset($context['user_id']) ? User::find($context['user_id']) : null;
            if (! $user) {
                return ResponseHelper::jsonResponse(false, 'Kode login Google tidak valid atau sudah kedaluwarsa.', null, 422);
            }

            $user->setAttribute('token', $user->createToken('auth_token')->plainTextToken);
            $user->setAttribute('permissions', $user->getAllPermissions()->pluck('name'));

            return ResponseHelper::jsonResponse(true, 'Login Berhasil', new UserResource($user), 200)
                ->withoutCookie(self::NONCE_COOKIE, self::NONCE_PATH);
        } catch (Throwable $e) {
            Log::error('GoogleAuth: Exchange failed', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Login Google gagal. Silakan coba lagi.', null, 500);
        }
    }

    private function pullOnce(string $key, ?Request $request = null): mixed
    {
        // Cache::pull is get + forget; serialize consumers to prevent concurrent replay.
        return Cache::lock($key.'.lock', 10)->block(5, function () use ($key, $request) {
            if ($request !== null && ! $this->matchesNonce($request, Cache::get($key))) {
                return null;
            }

            return Cache::pull($key);
        });
    }

    private function matchesNonce(Request $request, mixed $context): bool
    {
        $nonce = $request->cookie(self::NONCE_COOKIE);

        return is_array($context) && is_string($context['nonce'] ?? null)
            && is_string($nonce) && $nonce !== ''
            && hash_equals($context['nonce'], hash('sha256', $nonce));
    }

    private function loginError()
    {
        return redirect(rtrim(config('app.frontend_url'), '/').'/auth/login?error=google');
    }
}
