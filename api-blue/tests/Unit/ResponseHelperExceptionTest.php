<?php

namespace Tests\Unit;

use App\Helpers\ResponseHelper;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

/**
 * Controller di seluruh codebase punya idiom yang disengaja: repository
 * melempar `throw new Exception('pesan aman berbahasa Indonesia')` justru
 * supaya pesannya sampai ke pengguna. exceptionResponse() harus tetap
 * meneruskan pesan itu apa adanya.
 *
 * Satu-satunya yang harus disembunyikan adalah QueryException, karena
 * getMessage()-nya bisa memuat SQL mentah beserta binding-nya.
 */
class ResponseHelperExceptionTest extends TestCase
{
    public function test_a_deliberate_business_message_still_reaches_the_client(): void
    {
        $response = ResponseHelper::exceptionResponse(
            new \Exception('Semua produk harus berasal dari toko yang sama.')
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame('Semua produk harus berasal dari toko yang sama.', $body['message']);
    }

    public function test_a_query_exception_is_hidden_from_the_client(): void
    {
        $pdo = new PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'users.email'"
        );
        $e = new QueryException(
            'mysql',
            "select * from `users` where `email` = 'admin@gmail.com'",
            [],
            $pdo
        );

        $response = ResponseHelper::exceptionResponse($e);
        $body = json_decode($response->getContent(), true);

        $this->assertSame('Terjadi kesalahan pada server.', $body['message']);
        $this->assertStringNotContainsString('users', $body['message']);
        $this->assertStringNotContainsString('SQLSTATE', $body['message']);
        $this->assertStringNotContainsString('admin@gmail.com', $body['message']);
    }

    public function test_a_request_id_is_always_attached_for_correlation(): void
    {
        $response = ResponseHelper::exceptionResponse(new \Exception('apa saja'));

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_the_status_code_is_respected(): void
    {
        $response = ResponseHelper::exceptionResponse(new \Exception('Data tidak ditemukan'), 404);

        $this->assertSame(404, $response->getStatusCode());
    }
}
