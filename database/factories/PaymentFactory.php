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
            'transaction_code' => $method === 'banking' ? strtoupper(fake()->bothify('??######')) : null,
            'note' => fake()->optional()->sentence(),
            // 'invoice_id', 'payer_user_id', 'created_by_user_id', 'amount_paid' will be set in the Seeder
        ];
    }
}
