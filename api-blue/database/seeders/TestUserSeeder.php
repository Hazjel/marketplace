<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure roles exist
        if (!Role::where('name', 'buyer')->exists()) {
             Role::create(['name' => 'buyer', 'guard_name' => 'web']);
        }
        
        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'username' => 'adminuser',
                'password' => 'password', // Will be hashed by model cast
                'email_verified_at' => now(),
            ]
        );
        
        $user->assignRole('buyer');
        
        // Create buyer profile
        $user->buyer()->updateOrCreate(
             ['user_id' => $user->id],
             ['phone_number' => '081234567890']
        );

        $this->command->info('Test User Created: admin@example.com / password');
    }
}
