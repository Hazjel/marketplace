<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_create_product()
    {
        // Setup
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create();
        $user->guard_name = 'sanctum';
        $user->assignRole('store');
        
        $store = Store::create([
            'user_id' => $user->id,
            'name' => 'My Store',
            'username' => 'mystore',
            'city' => 'Bandung',
            'address' => 'Jalan abc',
            'phone' => '0812345678',
            'postal_code' => '40000',
            'about' => 'Desc'
        ]);

        $category = ProductCategory::create([
            'name' => 'Fashion',
            'slug' => 'fashion',
            'description' => 'Baju'
        ]);

        $payload = [
            'name' => 'Kaos Polos',
            'category_id' => $category->id,
            'description' => 'Kaos nyaman',
            'price' => 50000,
            'stock' => 100,
            'weight' => 200,
            'condition' => 'new',
            'galleries' => [
                UploadedFile::fake()->image('photo1.jpg')
            ]
        ];

        // Act
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/product', $payload);
        $response->dump();

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['slug' => 'kaos-polos']);
    }
}
