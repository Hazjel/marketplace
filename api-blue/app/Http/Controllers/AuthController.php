<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LoginStoreRequest;
use App\Http\Requests\RegisterStoreRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\AuthRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private AuthRepositoryInterface $authRepository;

    public function __construct(AuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function register(RegisterStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $user = $this->authRepository->register($request);

            event(new Registered($user));

            if (app()->isLocal()) {
                // Auto-verify email on local environment only
                $token = $user->token;
                unset($user->token);
                $user->markEmailAsVerified();
                $user->token = $token;
            }

            return ResponseHelper::jsonResponse(true, 'Registrasi Berhasil', new UserResource($user), 200);
        } catch (\Exception $e) {
            Log::error('Register failed', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Registrasi gagal. Silakan coba lagi.', null, 500);
        }
    }

    public function login(LoginStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $user = $this->authRepository->login($request);

            return ResponseHelper::jsonResponse(true, 'Login Berhasil', new UserResource($user), 200);
        } catch (\Exception $e) {
            $code = $e->getCode();

            // Hanya teruskan status HTTP yang valid; selain itu 500 generik
            if (! is_int($code) || $code < 400 || $code > 499) {
                Log::error('Login failed', ['error' => $e->getMessage()]);

                return ResponseHelper::jsonResponse(false, 'Login gagal. Silakan coba lagi.', null, 500);
            }

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, $code);
        }
    }

    public function me()
    {
        try {
            $user = $this->authRepository->me();

            return ResponseHelper::jsonResponse(true, 'Profile Berhasil Diambil', new UserResource($user), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|numeric|regex:/^08[0-9]{8,13}$/', // Added phone validation
            'profile_picture' => 'nullable|image|max:2048', // Max 2MB
            'password' => ['nullable', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*[0-9]).+$/'],
            'current_password' => 'required_with:password|current_password',
        ]);

        try {
            $user = $this->authRepository->updateProfile($request->all());

            return ResponseHelper::jsonResponse(true, 'Profile Berhasil Diupdate', new UserResource($user), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'notification_prefs' => 'sometimes|array',
            'notification_prefs.*' => 'boolean',
            'privacy_prefs' => 'sometimes|array',
            'privacy_prefs.*' => 'boolean',
        ]);

        try {
            $user = $this->authRepository->updateSettings($request->only(['notification_prefs', 'privacy_prefs']));

            return ResponseHelper::jsonResponse(true, 'Pengaturan Berhasil Disimpan', new UserResource($user), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function logout()
    {
        try {
            $user = $this->authRepository->logout();

            return ResponseHelper::jsonResponse(true, 'Logout Berhasil', new UserResource($user), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * SSO — dipanggil dari domain A (user sudah login) untuk mendapatkan token
     * exchange sekali-pakai yang dibawa lewat redirect ke domain B (blukios.store
     * <-> seller.blukios.store). Dipakai bukan cookie shared-domain karena kedua
     * app di subdomain berbeda dan auth di sini murni Sanctum Bearer token
     * (bukan cookie SPA, lihat config/cors.php supports_credentials=false).
     */
    public function ssoInitiate(Request $request)
    {
        $rawToken = Str::random(64);

        // Cache::pull di endpoint exchange nanti = atomic get+delete, jadi
        // token ini otomatis one-time-use. TTL pendek (30 detik) cukup untuk
        // sekali redirect browser, bukan disimpan lama seperti token sesi biasa.
        Cache::put("sso_exchange_{$rawToken}", Auth::id(), now()->addSeconds(30));

        return ResponseHelper::jsonResponse(true, 'success', ['exchange_token' => $rawToken], 200);
    }

    /**
     * SSO — dipanggil dari domain B (belum ada sesi) untuk menukar exchange
     * token jadi token Sanctum baru. Publik (tanpa auth:sanctum) karena
     * dipanggil sebelum user punya sesi di domain ini, tapi hanya valid kalau
     * bawa exchange_token yang benar dari ssoInitiate().
     */
    public function ssoExchange(Request $request)
    {
        $request->validate([
            'exchange_token' => 'required|string',
        ]);

        $userId = Cache::pull("sso_exchange_{$request->exchange_token}");

        if (! $userId) {
            return ResponseHelper::jsonResponse(false, 'Token SSO tidak valid atau sudah kedaluwarsa.', null, 401);
        }

        $user = User::find($userId);

        if (! $user) {
            return ResponseHelper::jsonResponse(false, 'Token SSO tidak valid atau sudah kedaluwarsa.', null, 401);
        }

        $user->token = $user->createToken('sso_auth_token')->plainTextToken;
        $user->permissions = $user->roles->flatMap->permissions->pluck('name');

        return ResponseHelper::jsonResponse(true, 'success', new UserResource($user), 200);
    }
}
