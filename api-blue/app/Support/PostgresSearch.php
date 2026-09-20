<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PostgresSearch
{
    /**
     * Konfigurasi teks yang dipakai semua index FTS dan semua query-nya.
     * 'simple' dipilih (bukan stemmer bahasa) karena katalog berisi nama
     * produk/toko -- merek, tipe, singkatan -- yang justru rusak kalau
     * di-stem. Nilai ini harus identik dengan yang dipakai di migrasi
     * index GIN, kalau berbeda index-nya tidak akan terpakai sama sekali.
     */
    public const CONFIG = 'simple';

    /**
     * Operator LIKE yang case-insensitive di semua driver yang dipakai repo.
     *
     * MySQL mencocokkan LIKE tanpa membedakan huruf besar/kecil karena
     * kolomnya ber-collation utf8mb4_unicode_ci. Postgres tidak: LIKE di sana
     * case-sensitive, jadi pencarian "Sepatu" berhenti menemukan "sepatu"
     * begitu database-nya pindah. ILIKE mengembalikan perilaku lama.
     *
     * sqlite (test suite) tidak mengenal ILIKE sama sekali, tapi LIKE-nya
     * memang sudah case-insensitive untuk ASCII -- jadi di sana 'like' sudah
     * benar dan tidak perlu diganti.
     */
    public static function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Ubah keyword bebas dari user menjadi argumen to_tsquery() yang aman.
     *
     * Padanan perilaku MySQL `MATCH ... AGAINST('+kata lain*' IN BOOLEAN MODE)`
     * yang dipakai sebelum migrasi ke Postgres: semua kata wajib ada (AND),
     * dan kata terakhir dicocokkan sebagai prefix supaya user masih dapat
     * hasil saat mengetik separuh kata.
     *
     * to_tsquery() melempar syntax error untuk input yang tidak valid, jadi
     * semua karakter operator ('&', '|', '!', ':', tanda kurung) dibuang di
     * sini -- bukan di-escape -- dan token disusun ulang dari nol.
     *
     * @return string|null null kalau tidak ada token tersisa; pemanggil wajib
     *                     jatuh ke LIKE, karena to_tsquery('') juga error.
     */
    public static function tsQuery(string $search): ?string
    {
        // Sisakan huruf/angka (termasuk non-ASCII) dan spasi; sisanya jadi spasi.
        $cleaned = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $search);
        $tokens = preg_split('/\s+/u', trim((string) $cleaned), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($tokens)) {
            return null;
        }

        $last = array_pop($tokens);
        $tokens[] = $last.':*';

        return implode(' & ', $tokens);
    }

    /**
     * Ekspresi tsvector untuk satu atau beberapa kolom teks.
     *
     * Dipakai baik oleh migrasi (saat membuat index GIN) maupun oleh query
     * pencarian, supaya keduanya tidak pernah berbeda. coalesce() wajib:
     * satu kolom NULL membuat seluruh hasil concat jadi NULL.
     *
     * @param  list<string>  $columns
     */
    public static function tsVector(array $columns): string
    {
        $parts = array_map(
            static fn (string $column): string => "coalesce({$column}, '')",
            $columns
        );

        return "to_tsvector('".self::CONFIG."', ".implode(" || ' ' || ", $parts).')';
    }
}
