<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LoginStoreRequest;
use App\Http\Requests\RegisterStoreRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\AuthRepositoryInterface;
use Illuminate\Http\Request;

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

            event(new \Illuminate\Auth\Events\Registered($user));

            if (app()->isLocal()) {
                // Auto-verify email on local environment only
                $token = $user->token;
                unset($user->token);
                $user->markEmailAsVerified();
                $user->token = $token;
            }

            return ResponseHelper::jsonResponse(true, 'Registrasi Berhasil', new UserResource($user), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Register failed', ['error' => $e->getMessage()]);

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
                \Illuminate\Support\Facades\Log::error('Login failed', ['error' => $e->getMessage()]);

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

    public function logout()
    {
        try {
            $user = $this->authRepository->logout();

            return ResponseHelper::jsonResponse(true, 'Logout Berhasil', new UserResource($user), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
