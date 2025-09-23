<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2022, 2026);
        $endYear = $startYear + 1;
        return [
            'name' => $startYear . '-' . $endYear,
            'start_date' => $startYear . '-09-05',
            'end_date' => $endYear . '-05-25',
            'is_active' => false,
        ];
    }
}
