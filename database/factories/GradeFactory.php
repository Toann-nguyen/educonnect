<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'score' => fake()->randomFloat(2, 4, 10),
            'type' => fake()->randomElement(['quiz', '15min', 'midterm', 'final']),
            'semester' => fake()->numberBetween(1, 2),
        ];
    }
}