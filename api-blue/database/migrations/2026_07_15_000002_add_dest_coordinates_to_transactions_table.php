<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot koordinat alamat tujuan saat checkout, supaya peta tracking
     * bisa menunjuk titik persis (bukan sekadar geocode nama kota).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('dest_latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('dest_longitude', 10, 7)->nullable()->after('dest_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['dest_latitude', 'dest_longitude']);
        });
    }
};
