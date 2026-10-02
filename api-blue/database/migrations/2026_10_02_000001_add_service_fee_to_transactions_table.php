<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flat buyer service fee kept by the platform (config marketplace.buyer_service_fee).
 * Older transactions had none, hence the 0 default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('service_fee', 26, 2)->default(0)->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('service_fee');
        });
    }
};
