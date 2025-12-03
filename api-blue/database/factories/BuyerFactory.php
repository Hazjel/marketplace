<?php

namespace Database\Factories;

use App\Models\Buyer;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Helpers\ImageHelper\ImageHelper;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Buyer>
 */
class BuyerFactory extends Factory
{
    protected $model = Buyer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $imageHelper = new ImageHelper;

        return [
            'user_id' => User::factory()->hasAttached(
                config('permission.models.role')::where('name', 'buyer')->first(),
                [],
                'roles'
            ),
            
            'phone_number' => $this->faker->phoneNumber(),
        ];
    }
}
