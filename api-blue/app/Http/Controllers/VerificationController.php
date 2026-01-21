<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Invalid user'], 404);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
             return response()->json(['message' => 'Invalid verification link'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/auth/login?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/auth/login?verified=1');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ResponseHelper::jsonResponse(true, 'Email sudah terverifikasi.', null, 200);
        }

        $request->user()->sendEmailVerificationNotification();

        return ResponseHelper::jsonResponse(true, 'Link verifikasi telah dikirim!', null, 200);
    }
}
