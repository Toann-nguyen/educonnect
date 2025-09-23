<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Học phí ' . fake()->monthName() . ' năm ' . fake()->year(),
            'amount' => fake()->randomElement([1500000, 2000000, 2500000]),
            'due_date' => fake()->dateTimeBetween('+5 days', '+1 month'),
            'status' => 'unpaid',
            // 'student_id' will be set in the Seeder
        ];
    }
}
