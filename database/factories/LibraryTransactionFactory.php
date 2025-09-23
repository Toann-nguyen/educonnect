<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LibraryTransaction>
 */
class LibraryTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $borrowDate = fake()->dateTimeThisYear('-1 month');
        return [
            'borrow_date' => $borrowDate,
            'due_date' => Carbon::parse($borrowDate)->addDays(14), // Hạn trả là 14 ngày sau
            'return_date' => null,
            'status' => 'borrowed',
            // 'book_id' and 'user_id' will be set in the Seeder
        ];
    }
    /**
     * Indicate that the book has been returned.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */

    public function returned(): Factory
    {
        return $this->state(function (array $attributes) {
            $returnDate = Carbon::parse($attributes['borrow_date'])->addDays(fake()->numberBetween(5, 20));
            return [
                'return_date' => $returnDate,
                'status' => $returnDate->isAfter($attributes['due_date']) ? 'overdue' : 'returned',
            ];
        });
    }
}
