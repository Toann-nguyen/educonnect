<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Sự kiện: ' . fake()->sentence(3),
            'description' => fake()->paragraph(3),
            'date' => fake()->dateTimeBetween('+1 week', '+3 months'),
        ];
    }
}
