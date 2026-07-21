<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Dulu registerStore() menghapus role 'buyer' saat user bikin toko.
     * Sejak dukung dual-role ala Shopee, user store harus tetap punya
     * role buyer juga — migrasi ini restore role buyer utk user existing
     * yang sudah kadung kehilangan role tsb.
     */
    public function up(): void
    {
        if (! Role::where('name', 'buyer')->exists()) {
            return;
        }

        User::role('store')
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'buyer');
            })
            ->get()
            ->each(function (User $user) {
                $user->assignRole('buyer');

                if (! $user->buyer) {
                    $user->buyer()->create([
                        'phone_number' => $user->store->phone ?? null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Tidak reversible dengan aman — tidak tahu user mana yang
        // buyer role-nya asli vs hasil restore migrasi ini.
    }
};
