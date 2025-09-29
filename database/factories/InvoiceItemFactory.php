<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(500000, 2000000);
        $quantity = 1;

        return [
            'description' => fake()->sentence(),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total_amount' => $unitPrice * $quantity,
            'note' => fake()->optional()->sentence()
        ];
    }
}
