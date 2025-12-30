<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ProductCategoryTest extends TestCase
{
    // Gunakan RefreshDatabase agar database di-reset setiap kali test jalan (bersih)
    use RefreshDatabase;

    public function test_admin_can_create_product_category()
    {
        // 1. Setup Environment (Create Admin User)
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // 2. Define Data
        $payload = [
            'name' => 'Elektronik',
            'slug' => 'elektronik',
            'description' => 'Kategori Elektronik',
            'image' => null // Boleh null jika di controller handle nullable
        ];

        // 3. Act (Hit API)
        $response = $this->actingAs($admin)->postJson('/api/product-category', $payload);

        // 4. Assert (Verifikasi Hasil)
        $response->assertStatus(201);
        $this->assertDatabaseHas('product_categories', ['name' => 'Elektronik']);
    }

    public function test_can_create_child_category_relationship()
    {
        // 1. Setup
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Buat Parent Category dulu
        $parent = ProductCategory::create([
            'name' => 'Elektronik', 
            'slug' => 'elektronik',
            'description' => 'Induk Kategori'
        ]);

        // 2. Hit API create Child
        $response = $this->actingAs($admin)->postJson('/api/product-category', [
            'name' => 'Laptop',
            'slug' => 'laptop',
            'description' => 'Laptop Gaming dan Kerja',
            'parent_id' => $parent->id // Link ke parent
        ]);

        // 3. Assert
        $response->assertStatus(201);
        
        // Cek database apakah Laptop punya parent_id yg benar
        $this->assertDatabaseHas('product_categories', [
            'name' => 'Laptop',
            'parent_id' => $parent->id
        ]);
    }
}
