<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentConductScore>
 */
class StudentConductScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $penaltyPoints = fake()->numberBetween(0, 20);

        // Tính conduct_grade dựa trên penalty_points
        if ($penaltyPoints == 0) {
            $conductGrade = 'excellent';
        } elseif ($penaltyPoints <= 5) {
            $conductGrade = 'good';
        } elseif ($penaltyPoints <= 15) {
            $conductGrade = 'average';
        } else {
            $conductGrade = 'weak';
        }

        $comments = [
            'excellent' => 'Học sinh có ý thức kỷ luật tốt, chấp hành nghiêm túc nội quy',
            'good' => 'Học sinh chấp hành tốt nội quy, có một số vi phạm nhỏ',
            'average' => 'Học sinh cần cố gắng hơn trong việc chấp hành nội quy',
            'weak' => 'Học sinh thường xuyên vi phạm nội quy, cần sự giám sát của gia đình'
        ];

        return [
            'semester' => fake()->numberBetween(1, 2),
            'total_penalty_points' => $penaltyPoints,
            'conduct_grade' => $conductGrade,
            'teacher_comment' => $comments[$conductGrade],
            'approved_at' => fake()->boolean(70) ? now()->subDays(rand(1, 30)) : null,
        ];
    }

    /** State: Điểm hạnh kiểm xuất sắc */
    public function excellent(): static
    {
        return $this->state(fn(array $attributes) => [
            'total_penalty_points' => 0,
            'conduct_grade' => 'excellent',
        ]);
    }

    /** State: Điểm hạnh kiểm tốt */
    public function good(): static
    {
        return $this->state(fn(array $attributes) => [
            'total_penalty_points' => fake()->numberBetween(1, 5),
            'conduct_grade' => 'good',
        ]);
    }
}
