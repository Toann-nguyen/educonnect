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
            // CỘT MỚI: Tạo một số hóa đơn ngẫu nhiên, duy nhất
            'invoice_number' => 'INV-' . fake()->unique()->randomNumber(6),

            // Các cột cũ
            'due_date' => fake()->dateTimeBetween('+5 days', '+1 month'),
            'status' => 'unpaid',

            // CỘT MỚI: Tạm thời để là 0, Seeder sẽ tính toán và ghi đè
            'total_amount' => 0,
            'paid_amount' => 0,

            // CỘT MỚI: issued_by và student_id sẽ do Seeder cung cấp
            'notes' => fake()->optional(0.5)->sentence(), // 50% hóa đơn có ghi chú

        ];
    }
}
