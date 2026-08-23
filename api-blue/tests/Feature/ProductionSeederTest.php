<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Yang dijalankan deployment tidak boleh membuat akun.
 *
 * Entrypoint dulu menjalankan DatabaseSeeder lengkap saat first-time setup,
 * yang memanggil UserSeeder dan membuat admin/buyer/seller dengan password
 * yang tertulis di repositori publik ini.
 */
class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_it_creates_roles_and_permissions(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertGreaterThan(0, Permission::count());
        foreach (['admin', 'buyer', 'store'] as $role) {
            $this->assertNotNull(Role::where('name', $role)->first(), "role {$role} tidak dibuat");
        }
    }

    public function test_it_creates_no_user_accounts(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_it_never_creates_the_documented_demo_logins(): void
    {
        $this->seed(ProductionSeeder::class);

        foreach (['admin@gmail.com', 'buyer@gmail.com', 'seller@gmail.com'] as $email) {
            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_it_can_run_twice_without_failing(): void
    {
        // Entrypoint memanggilnya pada setiap first-time setup, dan pemulihan
        // manual bisa memanggilnya lagi di database yang sudah terisi.
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);

        $this->assertSame(0, User::count());
        $this->assertGreaterThan(0, Role::count());
    }
}
