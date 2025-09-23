<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->numberBetween(2, 7), // Thứ 2 -> Thứ 7
            'period' => fake()->numberBetween(1, 10),    // Tiết 1 -> 10
            'room' => 'P' . fake()->numberBetween(101, 309),
        ];
    }
}
