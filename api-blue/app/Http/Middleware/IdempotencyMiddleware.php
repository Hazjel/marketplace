<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Mencegah satu operasi terkirim dua kali karena klik ganda, jaringan labil,
 * atau retry aplikasi. Setiap request mutasi wajib membawa header
 * `X-Idempotency-Key`.
 *
 * Kuncinya dipesan secara ATOMIK. Versi sebelumnya memeriksa Cache::has lalu
 * memproses lalu Cache::put — tiga langkah terpisah, sehingga dua request yang
 * datang nyaris bersamaan sama-sama melihat kunci kosong dan sama-sama lolos.
 * Untuk checkout artinya dua transaksi, stok terpotong dua kali, dan pembeli
 * ditagih dua kali. Cache::add pada Redis adalah SET NX, jadi hanya satu
 * request yang bisa memenangkannya.
 */
class IdempotencyMiddleware
{
    /**
     * Selama request masih diproses, kuncinya ditahan sebentar saja. Kalau
     * proses mati di tengah tanpa sempat membereskan, penahan ini kedaluwarsa
     * sendiri sehingga pembeli tidak terkunci 24 jam.
     */
    private const IN_FLIGHT_TTL_MINUTES = 5;

    private const RESULT_TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): mixed
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (! $idempotencyKey) {
            return ResponseHelper::jsonResponse(
                false,
                'Header X-Idempotency-Key wajib disertakan untuk operasi ini.',
                null,
                422
            );
        }

        $cacheKey = 'idempotency:'.auth()->id().':'.$idempotencyKey;

        if ($replay = $this->replayOf(Cache::get($cacheKey))) {
            return $replay;
        }

        // Pemesanan atomik: hanya satu request yang mendapat true.
        $reserved = Cache::add(
            $cacheKey,
            ['state' => 'in_flight'],
            now()->addMinutes(self::IN_FLIGHT_TTL_MINUTES)
        );

        if (! $reserved) {
            // Kalah balapan. Kalau pemenangnya sudah selesai, putar ulang
            // hasilnya; kalau masih berjalan, tolak alih-alih memproses ganda.
            if ($replay = $this->replayOf(Cache::get($cacheKey))) {
                return $replay;
            }

            return ResponseHelper::jsonResponse(
                false,
                'Permintaan dengan kunci yang sama sedang diproses. Mohon tunggu sebentar.',
                null,
                409
            );
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Jangan sandera kunci karena kegagalan — pembeli harus bisa
            // mencoba lagi dengan kunci yang sama.
            Cache::forget($cacheKey);

            throw $e;
        }

        $statusCode = $response->getStatusCode();
        $responseData = json_decode($response->getContent(), true);

        if ($statusCode >= 200 && $statusCode < 300 && $responseData) {
            $responseData['code'] = $statusCode;

            Cache::put(
                $cacheKey,
                ['state' => 'done', 'response' => $responseData],
                now()->addHours(self::RESULT_TTL_HOURS)
            );

            return $response;
        }

        // Respons gagal tidak disimpan supaya bisa diulang.
        Cache::forget($cacheKey);

        return $response;
    }

    /**
     * Mengubah entri cache menjadi respons ulangan, kalau entri itu memang
     * hasil yang sudah selesai.
     *
     * Entri dari versi lama middleware ini tidak punya 'state' dan menyimpan
     * body-nya di level atas, jadi tetap dikenali agar kunci yang sudah beredar
     * tidak mendadak diproses ulang setelah deploy.
     */
    private function replayOf(mixed $cached): mixed
    {
        if (! is_array($cached)) {
            return null;
        }

        if (($cached['state'] ?? null) === 'in_flight') {
            return null;
        }

        $body = ($cached['state'] ?? null) === 'done'
            ? $cached['response']
            : $cached;

        if (! is_array($body)) {
            return null;
        }

        return response()->json($body, $body['code'] ?? 200)
            ->header('X-Idempotency-Replayed', 'true');
    }
}
