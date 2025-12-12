<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = Permission::all()->filter(function ($permission) {
            return !in_array($permission->name, [
                'product-create',
                'product-edit',
                'product-delete'
            ]);
        });

        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'sanctum'
        ])->givePermissionTo($permissions);

        Role::firstOrCreate([
            'name' => 'buyer',
            'guard_name' => 'sanctum'
        ])->givePermissionTo([
            'dashboard-menu',

            'store-list',

            'product-category-list',

            'product-list',

            'transaction-menu',
            'transaction-list',
            'transaction-create',
            'transaction-edit',

            'product-review-list',
            'product-review-create'
        ]);

        Role::firstOrCreate([
            'name' => 'store',
            'guard_name' => 'sanctum'
        ])->givePermissionTo([
            'dashboard-menu',

            'store-menu',
            'store-list',
            'store-create',
            'store-edit',

            'store-balance-menu',
            'store-balance-list',

            'store-balance-history-list',

            'withdrawal-list',
            'withdrawal-create',
            'withdrawal-edit',
            'withdrawal-delete',

            'product-category-list',

            'product-menu',
            'product-list',
            'product-create',
            'product-edit',
            'product-delete',

            'transaction-menu',
            'transaction-list',
            'transaction-create',
            'transaction-edit',
            'transaction-delete',

            'product-review-menu',
            'product-review-list',
            'product-review-create'
        ]);
    }
}
