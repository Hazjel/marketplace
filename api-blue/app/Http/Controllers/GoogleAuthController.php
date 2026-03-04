<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        Log::info('GoogleAuth: Redirect Hit', [
            'platform' => request('platform'),
            'session_id' => session()->getId()
        ]);
        
        if (request()->has('platform')) {
            // Use 'state' parameter to persist platform across redirects (stateless)
            // This is more reliable than sessions for mobile deep links
            $platform = request('platform');
            return Socialite::driver('google')
                ->with([
                    'state' => "platform={$platform}",
                    'prompt' => 'select_account' // Force account chooser
                ])
                ->redirect();
        }
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account']) // Apply to web as well
            ->redirect();
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

            // Redirect Logic based on Platform
            // Extract platform from 'state' parameter if available
            $platform = null;
            if (request()->has('state')) {
                parse_str(request('state'), $stateParams);
                if (isset($stateParams['platform'])) {
                    $platform = $stateParams['platform'];
                }
            }
            
            // Fallback to session if state is empty (for web or legacy flows)
            if (!$platform) {
                $platform = session('auth_platform');
            }

            Log::info('GoogleAuth: Callback Hit', [
                 'platform' => $platform,
                 'state_param' => request('state'),
                 'session_id' => session()->getId()
            ]);

            $token = $user->token;
            
            if ($platform === 'mobile') {
                return redirect("mobileblue://callback?token={$token}&username={$user->username}");
            }

            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

            return redirect("{$frontendUrl}/auth/google/callback?token={$token}&username={$user->username}");

        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::jsonResponse(false, 'Google Login Failed: ' . $e->getMessage(), null, 500);
        }
    }
}
