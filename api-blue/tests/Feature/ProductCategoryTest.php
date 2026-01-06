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
    use RefreshDatabase;

    public function test_admin_can_create_product_category()
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $payload = [
            'name' => 'Elektronik',
            'slug' => 'elektronik',
            'description' => 'Kategori Elektronik',
            'image' => null
        ];

        $response = $this->actingAs($admin)->postJson('/api/product-category', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_categories', ['name' => 'Elektronik']);
    }

    public function test_can_create_child_category_relationship()
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $parent = ProductCategory::create([
            'name' => 'Elektronik', 
            'slug' => 'elektronik',
            'description' => 'Induk Kategori'
        ]);

        $response = $this->actingAs($admin)->postJson('/api/product-category', [
            'name' => 'Laptop',
            'slug' => 'laptop',
            'description' => 'Laptop Gaming dan Kerja',
            'parent_id' => $parent->id
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('product_categories', [
            'name' => 'Laptop',
            'parent_id' => $parent->id
        ]);
    }
}
