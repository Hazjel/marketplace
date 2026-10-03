<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private function storeWithProduct(string $username, bool $active, ProductCategory $category): Product
    {
        $store = Store::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Toko '.$username,
            'username' => $username,
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '0812',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
            'is_active' => $active,
        ]);

        return Product::create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Produk '.$username,
            'slug' => 'produk-'.$username,
            'description' => 'Test',
            'price' => 10000,
            'stock' => 5,
            'weight' => 0.5,
            'condition' => 'new',
        ]);
    }

    public function test_lists_what_visitors_can_open_and_nothing_else(): void
    {
        config(['marketplace.storefront_url' => 'https://blukios.store']);
        Cache::forget('sitemap.xml');
        $category = ProductCategory::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'description' => 'x']);
        $this->storeWithProduct('tokoaktif', true, $category);
        $this->storeWithProduct('tokomati', false, $category);

        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'sitemap is not valid XML');
        $locs = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));

        $this->assertContains('https://blukios.store/', $locs);
        $this->assertContains('https://blukios.store/product/produk-tokoaktif', $locs);
        $this->assertContains('https://blukios.store/store/tokoaktif', $locs);
        $this->assertContains('https://blukios.store/browse-category/elektronik', $locs);

        // Deactivated stores and their products answer 404: never list them.
        $this->assertNotContains('https://blukios.store/product/produk-tokomati', $locs);
        $this->assertNotContains('https://blukios.store/store/tokomati', $locs);
    }
}
