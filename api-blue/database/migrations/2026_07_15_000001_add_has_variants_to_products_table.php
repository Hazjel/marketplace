<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom has_variants dipakai model/resource/controller sejak fitur varian
     * Mongo, tapi tidak pernah ada migration-nya — instalasi fresh gagal saat
     * insert produk. Guard hasColumn untuk DB lama yang kolomnya sudah
     * ditambahkan manual.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'has_variants')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('has_variants')->default(false)->after('condition');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'has_variants')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('has_variants');
            });
        }
    }
};
