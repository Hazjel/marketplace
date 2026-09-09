<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(rand(2, 4), true);
        $conditions = ['new', 'second'];

        return [
            'store_id' => Store::factory(),
            'product_category_id' => ProductCategory::inRandomOrder()->first()->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraphs(rand(2, 4), true),
            'condition' => $this->faker->randomElement($conditions),
            // Whole rupiah only: fractional prices violate the money
            // contract (docs/money-contract.md) and would break the
            // Money-backed transaction_details.subtotal pilot.
            'price' => $this->faker->numberBetween(10_000, 50_000_000),
            'weight' => $this->faker->randomFloat(2, 0.1, 10),
            'stock' => $this->faker->numberBetween(1, 100),
        ];
    }
}
