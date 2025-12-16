<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FixBuyerPermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:buyer-permission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant missing product review permissions to buyer role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $role = Role::where('name', 'buyer')->where('guard_name', 'sanctum')->first();

        if (!$role) {
            $this->error('Buyer role not found for sanctum guard.');
            return;
        }

        $permissions = [
            'product-review-list',
            'product-review-create',
            'transaction-list' // ensuring this exists too as I saw checks for it
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'sanctum'
            ]);
            $role->givePermissionTo($permission);
        }

        $this->info('Permissions product-review-list, product-review-create, and transaction-list granted to buyer role.');
    }
}
