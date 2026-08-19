<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_query_returns_empty_results_without_querying()
    {
        $response = $this->getJson('/api/search/suggestions?q=a');

        $response->assertStatus(200)
            ->assertJsonPath('data.products', [])
            ->assertJsonPath('data.categories', [])
            ->assertJsonPath('data.stores', []);
    }

    public function test_matches_products_categories_and_stores()
    {
        $store = Store::factory()->create(['name' => 'Toko Sepatu Jaya']);
        $category = ProductCategory::create(['name' => 'Sepatu Lari', 'slug' => 'sepatu-lari', 'description' => 'd']);
        Product::create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Sepatu Lari Merah',
            'slug' => 'sepatu-lari-merah',
            'description' => 'd',
            'price' => 100000,
            'stock' => 5,
            'weight' => 1,
            'condition' => 'new',
        ]);

        $response = $this->getJson('/api/search/suggestions?q=sepatu');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['products']);
        $this->assertSame('Sepatu Lari Merah', $data['products'][0]['name']);
        $this->assertCount(1, $data['categories']);
        $this->assertCount(1, $data['stores']);
    }

    public function test_prefix_match_ranks_before_mid_string_match()
    {
        $store = Store::factory()->create();
        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-rank', 'description' => 'd']);
        Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Case iPhone 15', 'slug' => 'case-iphone-15', 'description' => 'd',
            'price' => 50000, 'stock' => 5, 'weight' => 1, 'condition' => 'new',
        ]);
        Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'iPhone 15 Pro', 'slug' => 'iphone-15-pro', 'description' => 'd',
            'price' => 15000000, 'stock' => 5, 'weight' => 1, 'condition' => 'new',
        ]);

        $response = $this->getJson('/api/search/suggestions?q=iphone');

        $names = collect($response->json('data.products'))->pluck('name')->all();
        $this->assertSame('iPhone 15 Pro', $names[0]);
    }
}
