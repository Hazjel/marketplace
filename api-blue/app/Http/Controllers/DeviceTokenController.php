<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Registers/unregisters the calling device's FCM token, so the (not yet
 * built) push-sending side has somewhere to read targets from. Deliberately
 * separate from any "send" logic — that needs the Firebase Admin SDK
 * credential, this doesn't.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:255',
            'platform' => 'nullable|string|in:android,ios,web',
        ]);

        // Upsert by token, not by (user_id, token) — the same physical
        // token can only ever point at one signed-in user at a time. If
        // this device previously belonged to a different account, this
        // reassigns it rather than leaving a stale row under the old user.
        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => Auth::id(), 'platform' => $data['platform'] ?? null],
        );

        return ResponseHelper::jsonResponse(true, 'Device token terdaftar', ['id' => $deviceToken->id], 200);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:255',
        ]);

        // Scoped to the caller's own user_id — never lets one account
        // unregister a token that currently belongs to someone else.
        DeviceToken::where('token', $data['token'])
            ->where('user_id', Auth::id())
            ->delete();

        return ResponseHelper::jsonResponse(true, 'Device token dihapus', null, 200);
    }
}
