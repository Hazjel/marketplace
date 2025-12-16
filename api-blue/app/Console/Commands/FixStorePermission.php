<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class FixStorePermission extends Command
{
    protected $signature = 'fix:store-permission';
    protected $description = 'Grant product-category-create permission to store role';

    public function handle()
    {
        $role = Role::where('name', 'store')->where('guard_name', 'sanctum')->first();
        
        if (!$role) {
            $this->error('Role store not found!');
            return;
        }

        $permissions = [
            'product-category-create',
            'product-review-list'
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'sanctum'
            ]);
            $role->givePermissionTo($permission);
        }
        
        $this->info('Permissions product-category-create and product-review-list granted to store role.');
    }
}
