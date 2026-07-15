<?php

namespace App\Repositories;

use App\Interfaces\AuthRepositoryInterface;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthRepository implements AuthRepositoryInterface
{
    public function register(array $data)
    {
        DB::beginTransaction();

        try {
            $user = new User;

            if (isset($data['profile_picture'])) {
                $user->profile_picture = $data['profile_picture']->store('assets/user', 'public');
            }

            $user->name = $data['name'];

            // Auto-generate username
            $slug = Str::slug($data['name']);
            $count = 1;
            while (User::where('username', $slug)->exists()) {
                $slug = Str::slug($data['name']).'-'.$count;
                $count++;
            }
            $user->username = $slug;

            $user->email = $data['email'];
            $user->password = bcrypt($data['password']);
            $user->save();

            // Role store hanya lewat alur register-store (butuh email verified)
            $user->assignRole('buyer');

            // Always create buyer profile
            $user->buyer()->create([
                'phone_number' => $data['phone_number'] ?? null,
            ]);

            $user->token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function login(array $data)
    {
        $lockKey = 'login_locked_'.md5(strtolower($data['email'] ?? ''));
        $attemptsKey = 'login_attempts_'.md5(strtolower($data['email'] ?? ''));

        // Lockout per-IP: ambang lebih longgar (20x) supaya NAT bersama tidak
        // gampang kena, tapi brute force lintas-akun dari satu IP tetap tertahan.
        $ip = request()->ip() ?? 'unknown';
        $ipLockKey = 'login_ip_locked_'.md5($ip);
        $ipAttemptsKey = 'login_ip_attempts_'.md5($ip);

        if (Cache::get($lockKey) || Cache::get($ipLockKey)) {
            throw new Exception('Akun dikunci sementara karena terlalu banyak percobaan login gagal.', 429);
        }

        DB::beginTransaction();

        try {
            if (! Auth::guard('web')->attempt($data)) {
                $attempts = (int) Cache::get($attemptsKey, 0) + 1;
                Cache::put($attemptsKey, $attempts, now()->addMinutes(15));
                if ($attempts >= 5) {
                    Cache::put($lockKey, true, now()->addMinutes(15));
                }

                $ipAttempts = (int) Cache::get($ipAttemptsKey, 0) + 1;
                Cache::put($ipAttemptsKey, $ipAttempts, now()->addMinutes(15));
                if ($ipAttempts >= 20) {
                    Cache::put($ipLockKey, true, now()->addMinutes(15));
                }

                throw new Exception('Email atau password salah.', 401);
            }

            Cache::forget($attemptsKey);
            Cache::forget($lockKey);

            $user = Auth::user();
            $user->token = $user->createToken('auth_token')->plainTextToken;
            $user->permissions = $user->roles->flatMap->permissions->pluck('name');

            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    public function updateProfile(array $data)
    {
        DB::beginTransaction();

        try {
            if (! Auth::check()) {
                throw new Exception('Unauthorized', 401);
            }

            $user = Auth::user();

            $user->name = $data['name'];

            // Handle Profile Picture Upload
            if (isset($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
                // Delete old image if exists and is not a default/google image (optional check)
                if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                    Storage::disk('public')->delete($user->profile_picture);
                }

                $path = $data['profile_picture']->store('assets/user', 'public');
                $user->profile_picture = $path;
            }

            if (isset($data['password']) && ! empty($data['password'])) {
                $user->password = bcrypt($data['password']);
            }

            $user->save();

            // Update Phone Number based on Role
            if (isset($data['phone_number'])) {
                if ($user->hasRole('buyer')) {
                    $user->buyer()->updateOrCreate(
                        ['user_id' => $user->id],
                        ['phone_number' => $data['phone_number']]
                    );
                } elseif ($user->hasRole('store')) {
                    $user->store()->updateOrCreate(
                        ['user_id' => $user->id],
                        ['phone' => $data['phone_number']]
                    );
                }
            }

            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function me()
    {
        DB::beginTransaction();

        try {
            if (! Auth::check()) {
                throw new Exception('Unauthorized');
            }

            $user = Auth::user();
            $user->load(['buyer', 'store']);
            $user->permissions = $user->roles->flatMap->permissions->pluck('name');

            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function logout()
    {
        DB::beginTransaction();

        try {
            if (! Auth::check()) {
                throw new Exception('Unauthorized');
            }

            $user = Auth::user();

            $user->tokens()->delete();

            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }
}
