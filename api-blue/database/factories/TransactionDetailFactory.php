<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionDetail>
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

        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => $product->id,
            'qty' => $qty,
            'subtotal' => Money::fromDecimalString((string) $product->price)->multiplyByQty($qty),
        ];
    }
}
