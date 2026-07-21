<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferensi notifikasi & privasi user (settings). Sebelumnya halaman
     * NotificationSettings/PrivacySettings hanya mockup (setTimeout, tanpa
     * backend). Simpan sebagai JSON nullable — null artinya pakai default.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'notification_prefs')) {
                $table->json('notification_prefs')->nullable()->after('last_seen_at');
            }
            if (! Schema::hasColumn('users', 'privacy_prefs')) {
                $table->json('privacy_prefs')->nullable()->after('notification_prefs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['notification_prefs', 'privacy_prefs'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
