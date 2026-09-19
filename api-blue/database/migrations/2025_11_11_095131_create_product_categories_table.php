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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('image')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->string('description');
            $table->timestamps();
        });

        // FK self-reference sengaja dipasang di panggilan terpisah, bukan di
        // dalam Schema::create() di atas. Laravel memancarkan perintah sesuai
        // urutan deklarasi, sedangkan PRIMARY KEY dari modifier ->primary()
        // ditambahkan belakangan -- jadi kalau foreign() ikut di dalam blok
        // itu, ALTER TABLE ADD FOREIGN KEY jalan sebelum primary key-nya ada.
        // MySQL menerimanya, Postgres menolak dengan
        // "there is no unique constraint matching given keys for referenced
        // table". Di sini tabel (beserta primary key-nya) sudah utuh.
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('product_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
