<?php

namespace App\Repositories;

use App\Interfaces\AuthRepositoryInterface;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthRepository implements AuthRepositoryInterface
{
    public function register(array $data)
    {
        DB::beginTransaction();

        try {
            $user = new User;
            $user->profile_picture = $data['profile_picture']->store('assets/user', 'public');
            $user->name = $data['name'];
            
            // Auto-generate username
            $slug = Str::slug($data['name']);
            $count = 1;
            while (User::where('username', $slug)->exists()) {
                $slug = Str::slug($data['name']) . '-' . $count;
                $count++;
            }
            $user->username = $slug;

            $user->email = $data['email'];
            $user->password = bcrypt($data['password']);
            $user->save();

            $user->assignRole($data['role']);

            if ($data['role'] == 'buyer') {
                $user->buyer()->create([
                    'phone_number' => $data['phone_number']
                ]);
            }

            if ($data['role'] == 'store') {
                $user->store()->create([
                    'name' => $data['name'],
                    'phone' => $data['phone_number'],
                    'slug' => Str::slug($data['name']) . '-' . Str::random(5),
                ]);
            }


            $user->token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }


    public function login(array $data)
    {
        DB::beginTransaction();

        try {
            if (!Auth::guard('web')->attempt($data)) {
                throw new Exception('Unauthorized', 401);
            }

            $user = Auth::user();
            $user->token = $user->createToken('auth_token')->plainTextToken;
            $user->permissions = $user->roles->flatMap->permissions->pluck('name');

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage(), $e->getCode());
        }
    }



    public function updateProfile(array $data)
    {
        DB::beginTransaction();

        try {
            if (!Auth::check()) {
                throw new Exception('Unauthorized', 401);
            }

            $user = Auth::user();

            $user->name = $data['name'];

            // Handle Profile Picture Upload
            if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
                // Delete old image if exists and is not a default/google image (optional check)
                if ($user->profile_picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_picture)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_picture);
                }

                $path = $data['profile_picture']->store('assets/user', 'public');
                $user->profile_picture = $path;
            }

            if (isset($data['password']) && !empty($data['password'])) {
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
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function me()
    {
        DB::beginTransaction();

        try {
            if (!Auth::check()) {
                throw new Exception('Unauthorized');
            }

            $user = Auth::user();
            $user->permissions = $user->roles->flatMap->permissions->pluck('name');

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function logout()
    {
        DB::beginTransaction();

        try {
            if (!Auth::check()) {
                throw new Exception('Unauthorized');
            }

            $user = Auth::user();

            $user->tokens()->delete();

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }
}
