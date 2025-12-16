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
        // 1. Add receiving_proof to transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('receiving_proof')->nullable()->after('delivery_proof');
        });

        // 2. Add is_anonymous to product_reviews
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('review');
        });

        // 3. Create product_review_attachments table
        Schema::create('product_review_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_review_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_type'); // 'image' or 'video'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_review_attachments');

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn('is_anonymous');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('receiving_proof');
        });
    }
};
