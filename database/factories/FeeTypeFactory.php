<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeeType>
 */
class FeeTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->word()),
            'name' => fake()->words(3, true),
            'default_amount' => fake()->randomElement([500000, 1000000, 1500000, 30000000]),
            'is_active' => true,
        ];
    }
}
