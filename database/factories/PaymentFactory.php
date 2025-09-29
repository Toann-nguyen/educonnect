<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
     */
    public function definition(): array
    {
        $method = fake()->randomElement(['cash', 'banking']);
        return [
            'payment_date' => fake()->dateTimeThisMonth(),
            'payment_method' => $method,
            'amount_paid' => fake()->randomFloat(2, 100000, 5000000), // Random amount between 100,000 and 5,000,000
            'transaction_code' => $method === 'banking' ? strtoupper(fake()->bothify('??######')) : null,
            'note' => fake()->optional()->sentence(),
            // 'invoice_id', 'payer_user_id', 'created_by_user_id' will be set in the Seeder
        ];
    }
}
