<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Kirim link reset password ke email user.
     * Route: POST /api/password/forgot
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.exists'   => 'Email tidak terdaftar di sistem kami.',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return ResponseHelper::jsonResponse(
                true,
                'Link reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.',
                null,
                200
            );
        }

        // Throttled: user meminta terlalu sering
        if ($status === Password::RESET_THROTTLED) {
            return ResponseHelper::jsonResponse(
                false,
                'Terlalu banyak percobaan. Silakan tunggu beberapa saat sebelum mencoba lagi.',
                null,
                429
            );
        }

        return ResponseHelper::jsonResponse(
            false,
            'Gagal mengirim link reset password. Silakan coba lagi.',
            null,
            500
        );
    }

    /**
     * Proses reset password dengan token yang valid.
     * Route: POST /api/password/reset
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ], [
            'token.required'                 => 'Token reset tidak valid.',
            'email.required'                 => 'Email wajib diisi.',
            'email.email'                    => 'Format email tidak valid.',
            'password.required'              => 'Password baru wajib diisi.',
            'password.min'                   => 'Password minimal 8 karakter.',
            'password.confirmed'             => 'Konfirmasi password tidak cocok.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ResponseHelper::jsonResponse(
                true,
                'Password berhasil direset. Silakan login dengan password baru Anda.',
                null,
                200
            );
        }

        // Token tidak valid atau sudah kadaluarsa
        if ($status === Password::INVALID_TOKEN) {
            return ResponseHelper::jsonResponse(
                false,
                'Token reset password tidak valid atau sudah kadaluarsa. Silakan minta link baru.',
                null,
                422
            );
        }

        // Email tidak ditemukan
        if ($status === Password::INVALID_USER) {
            return ResponseHelper::jsonResponse(
                false,
                'Email tidak terdaftar di sistem kami.',
                null,
                404
            );
        }

        return ResponseHelper::jsonResponse(
            false,
            'Gagal mereset password. Silakan coba lagi.',
            null,
            500
        );
    }
}
