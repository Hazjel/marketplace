<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            DB::beginTransaction();

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                // Register new user
                $user = new User;
                $user->name = $googleUser->getName();
                $user->email = $googleUser->getEmail();
                $user->email_verified_at = now();
                $user->password = bcrypt(Str::random(16)); // Random password
                $user->profile_picture = $googleUser->getAvatar();
                
                // Generate Username
                $slug = Str::slug($user->name);
                $count = 1;
                while (User::where('username', $slug)->exists()) {
                    $slug = Str::slug($user->name) . '-' . $count;
                    $count++;
                }
                $user->username = $slug;
                
                $user->save();

                // Assign default role 'buyer'
                $user->assignRole('buyer');

                // Create default buyer profile (empty phone number for now)
                $user->buyer()->create([
                   'phone_number' => '' // Handle missing phone logic later
                ]);
            }

            // Login Logic
            $user->token = $user->createToken('auth_token')->plainTextToken;
            $user->permissions = $user->roles->flatMap->permissions->pluck('name');

            DB::commit();

            // Return redirect to frontend with token
            // Since this API is likely called by browser redirect, we should redirect back to Frontend App
            // Frontend URL: http://localhost:5173/auth/google/callback?token=...
            
            // However, typical API usage returns JSON. But for OAuth redirect, the browser is navigating here.
            // So we must return a Redirect response to the Frontend.
            
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            $token = $user->token;

            return redirect("{$frontendUrl}/auth/google/callback?token={$token}&username={$user->username}");

        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::jsonResponse(false, 'Google Login Failed: ' . $e->getMessage(), null, 500);
        }
    }
}
