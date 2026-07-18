<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinyal implisit buat sistem rekomendasi (collaborative filtering) --
     * user_id nullable karena guest (belum login) juga dicatat via session_id,
     * dibedain bobotnya dari wishlist/transaksi yang sinyalnya lebih kuat.
     */
    public function up(): void
    {
        Schema::create('product_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['user_id', 'product_id', 'viewed_at']);
            $table->index(['session_id', 'product_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_views');
    }
};
