<?php

namespace App\Http\Controllers;

use App\Support\OpsSignals;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives crashes from the web app (and later the mobile app), which
 * otherwise only ever reach the user's own console. Logged and counted for
 * `ops:check`; nothing is stored per user.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'source' => 'required|in:web,mobile',
            'message' => 'required|string|max:500',
            'url' => 'nullable|string|max:500',
            'stack' => 'nullable|string|max:4000',
            'release' => 'nullable|string|max:64',
        ]);

        // Path only: query strings carry reset tokens, emails and search terms.
        $path = isset($data['url']) ? (parse_url($data['url'], PHP_URL_PATH) ?: '/') : null;

        Log::warning('Client error', [
            'source' => $data['source'],
            'message' => $data['message'],
            'path' => $path,
            'release' => $data['release'] ?? null,
            'stack' => isset($data['stack']) ? Str::limit($data['stack'], 2000) : null,
            'user_id' => $request->user('sanctum')?->id,
        ]);

        OpsSignals::record(
            OpsSignals::CLIENT_ERROR,
            $data['source'].': '.Str::limit($data['message'], 150).($path ? ' @ '.$path : '')
        );

        return response()->noContent();
    }
}
