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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // A physical device's FCM token is globally unique — re-registering
            // it (e.g. app reopened, token refreshed) upserts in place rather
            // than accumulating duplicate rows. If the SAME token later shows
            // up under a DIFFERENT user (logout + different account login on
            // the same device), the upsert reassigns user_id so pushes follow
            // whoever is actually logged in on that device now.
            $table->string('token')->unique();
            $table->string('platform')->nullable(); // android | ios | web
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
