<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke()
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            // Unset -> "undefined" rather than a stale hardcoded number.
            'version' => config('app.version') ?: ($this->deployedCommit() ?? 'undefined'),
            'services' => [],
        ];

        // Check database
        try {
            DB::connection()->getPdo();
            $health['services']['database'] = 'connected';
        } catch (\Exception $e) {
            $health['services']['database'] = 'disconnected';
            $health['status'] = 'degraded';
        }

        // Check cache
        try {
            Cache::put('health_check', true, 10);
            $health['services']['cache'] = Cache::get('health_check') ? 'working' : 'failed';
        } catch (\Exception $e) {
            $health['services']['cache'] = 'failed';
            $health['status'] = 'degraded';
        }

        $statusCode = $health['status'] === 'ok' ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    // Written by the Jenkins Deploy stage right after `git reset`. Read per
    // request rather than via config: config is cached at container start,
    // and code in the api-blue bind mount goes live before any recreate.
    private function deployedCommit(): ?string
    {
        $path = storage_path('app/deployed-commit');
        if (! is_readable($path)) {
            return null;
        }

        $sha = trim((string) file_get_contents($path));

        return preg_match('/^[0-9a-f]{7,40}$/', $sha) === 1 ? $sha : null;
    }
}
