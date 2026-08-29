<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            // String, bukan uuid()/foreignId() -- variant tersimpan di
            // MongoDB (ProductVariantMongo), bukan MySQL, jadi tidak ada FK
            // lintas-database yang bisa dipasang. Nullable karena produk
            // tanpa varian (has_variants=false) tetap checkout normal
            // lewat products.price seperti sebelumnya.
            $table->string('variant_id')->nullable()->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropColumn('variant_id');
        });
    }
};
