<?php

namespace App\Helpers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResponseHelper
{
    // Keep existing for backward compatibility
    public static function jsonResponse($success, $message, $data, $statusCode): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function success($data = null, string $message = 'Berhasil', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error(string $message = 'Terjadi kesalahan', int $code = 500, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Titik tunggal untuk blok `catch (\Exception $e)` di controller yang
     * dulu langsung mengembalikan $e->getMessage() ke client.
     *
     * Codebase ini punya idiom yang disengaja: repository melempar
     * `throw new Exception('pesan aman berbahasa Indonesia')` justru supaya
     * pesannya sampai ke pengguna lewat jalur ini -- itu tetap dipertahankan
     * di sini. Yang ditutup cuma satu celah nyata: QueryException bisa
     * membawa SQL mentah beserta binding-nya di getMessage(), dan itu tidak
     * pernah dimaksudkan untuk dilihat client.
     *
     * Detail lengkap selalu dicatat ke log dengan request id, supaya
     * kegagalan generik tetap bisa ditelusuri tanpa membocorkan apa pun ke
     * response. Request id juga dikirim lewat header (bukan body), supaya
     * bentuk response untuk web/mobile yang sudah ada tidak berubah.
     */
    public static function exceptionResponse(Throwable $e, int $code = 500): JsonResponse
    {
        $requestId = (string) Str::uuid();

        Log::error('Unhandled exception', [
            'request_id' => $requestId,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $message = $e instanceof QueryException
            ? 'Terjadi kesalahan pada server.'
            : $e->getMessage();

        return self::jsonResponse(false, $message, null, $code)
            ->header('X-Request-Id', $requestId);
    }

    public static function paginated(LengthAwarePaginator $paginator, $resource, string $message = 'Data berhasil diambil'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'data' => $resource,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ], 200);
    }

    public static function validationError($errors, string $message = 'Validasi gagal'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }
}
