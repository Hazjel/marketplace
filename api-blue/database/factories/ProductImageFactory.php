<?php

namespace Database\Factories;

use App\Models\ProductImage;
use App\Models\Product;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /**
     * Product placeholder images from picsum.photos (reliable, no auth needed).
     * These are cached URLs that always return a valid image.
     */
    private static array $placeholderImages = [
        'https://picsum.photos/seed/blk1/800/800',
        'https://picsum.photos/seed/blk2/800/800',
        'https://picsum.photos/seed/blk3/800/800',
        'https://picsum.photos/seed/blk4/800/800',
        'https://picsum.photos/seed/blk5/800/800',
        'https://picsum.photos/seed/blk6/800/800',
        'https://picsum.photos/seed/blk7/800/800',
        'https://picsum.photos/seed/blk8/800/800',
        'https://picsum.photos/seed/blk9/800/800',
        'https://picsum.photos/seed/blk10/800/800',
        'https://picsum.photos/seed/blk11/800/800',
        'https://picsum.photos/seed/blk12/800/800',
        'https://picsum.photos/seed/blk13/800/800',
        'https://picsum.photos/seed/blk14/800/800',
        'https://picsum.photos/seed/blk15/800/800',
        'https://picsum.photos/seed/blk16/800/800',
        'https://picsum.photos/seed/blk17/800/800',
        'https://picsum.photos/seed/blk18/800/800',
        'https://picsum.photos/seed/blk19/800/800',
        'https://picsum.photos/seed/blk20/800/800',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'image' => $this->faker->randomElement(self::$placeholderImages),
            'is_thumbnail' => false,
        ];
    }

    public function thumbnail()
    {
        return $this->state(fn (array $attributes) => [
            'is_thumbnail' => true,
        ]);
    }
}
