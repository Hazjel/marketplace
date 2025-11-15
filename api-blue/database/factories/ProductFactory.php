<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
use App\Models\Store;
use App\Models\ProductCategory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
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
            'price' => $this->faker->randomFloat(2, 10000, 1000000),
            'weight' => $this->faker->randomFloat(2, 0.1, 10),
            'stock' => $this->faker->numberBetween(1, 100),
        ];
    }
}
