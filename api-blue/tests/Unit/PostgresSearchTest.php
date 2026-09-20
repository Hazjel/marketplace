<?php

namespace Tests\Unit;

use App\Support\PostgresSearch;
use Tests\TestCase;

/**
 * to_tsquery() bukan fungsi yang memaafkan: ia punya bahasa operator
 * sendiri ('&', '|', '!', ':', tanda kurung) dan melempar SQL syntax error
 * untuk input yang tidak valid -- termasuk string kosong. Karena keyword
 * datang langsung dari kotak pencarian pengguna, sanitasi di sini adalah
 * satu-satunya hal yang berdiri antara ketikan sembarang dan query gagal.
 *
 * Ekspresi tsvector diuji juga karena harus identik, sampai ke spasinya,
 * dengan yang dipakai migrasi saat membuat index GIN. Kalau keduanya
 * berbeda sedikit saja, Postgres tidak akan memakai index itu dan
 * pencarian diam-diam berubah jadi sequential scan -- pelan, tapi tetap
 * mengembalikan hasil yang benar, jadi tidak ada yang gagal untuk
 * memberi tahu kita.
 */
class PostgresSearchTest extends TestCase
{
    public function test_kata_terakhir_dicocokkan_sebagai_prefix(): void
    {
        $this->assertSame('sepatu:*', PostgresSearch::tsQuery('sepatu'));
    }

    public function test_semua_kata_wajib_ada_dan_hanya_yang_terakhir_prefix(): void
    {
        $this->assertSame('sepatu & lari:*', PostgresSearch::tsQuery('sepatu lari'));
    }

    public function test_operator_tsquery_dibuang_bukan_diteruskan(): void
    {
        // Tanpa pembersihan ini, '&' dan '|' dari pengguna akan sampai ke
        // to_tsquery() sebagai operator dan bikin query meledak.
        $this->assertSame('kaos & celana:*', PostgresSearch::tsQuery('kaos & celana'));
        $this->assertSame('drop & table:*', PostgresSearch::tsQuery("drop' | table"));
    }

    public function test_spasi_berlebih_tidak_menghasilkan_token_kosong(): void
    {
        $this->assertSame('spasi & banyak:*', PostgresSearch::tsQuery('  spasi   banyak  '));
    }

    public function test_keyword_tanpa_huruf_atau_angka_mengembalikan_null(): void
    {
        // Pemanggil wajib jatuh ke LIKE di kasus ini: to_tsquery('') sendiri
        // sudah error, jadi mengembalikan string kosong tidak akan membantu.
        $this->assertNull(PostgresSearch::tsQuery('!!!'));
        $this->assertNull(PostgresSearch::tsQuery(''));
    }

    public function test_ekspresi_tsvector_sama_dengan_yang_dipakai_index(): void
    {
        $this->assertSame(
            "to_tsvector('simple', coalesce(name, '') || ' ' || coalesce(description, ''))",
            PostgresSearch::tsVector(['name', 'description'])
        );

        $this->assertSame(
            "to_tsvector('simple', coalesce(name, ''))",
            PostgresSearch::tsVector(['name'])
        );
    }

    public function test_operator_like_case_insensitive_di_luar_postgres(): void
    {
        // Test suite berjalan di sqlite, yang LIKE-nya memang sudah
        // case-insensitive untuk ASCII dan tidak mengenal ILIKE sama sekali.
        $this->assertSame('like', PostgresSearch::likeOperator());
    }
}
