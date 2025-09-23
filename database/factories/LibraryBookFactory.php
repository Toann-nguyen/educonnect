<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LibraryBook>
 */
class LibraryBookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $total = fake()->numberBetween(5, 30);
        return [
            'title' => fake()->sentence(4),
            'author' => fake()->name(),
            'total_quantity' => $total,
            'available_quantity' => $total, // Ban đầu, số lượng có sẵn bằng tổng số
        ];
    }
}
