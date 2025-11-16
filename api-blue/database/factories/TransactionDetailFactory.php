<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\TransactionDetail;
use App\Models\Product;
use App\Models\Transaction;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionDetail>
 */
class TransactionDetailFactory extends Factory
{
    protected $model = TransactionDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 5);
        $product = Product::factory()->create();
        $subtotal = $product->price * $qty;

        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => $product->id,
            'qty' => $qty,
            'subtotal' => $subtotal,
        ];
    }
}
