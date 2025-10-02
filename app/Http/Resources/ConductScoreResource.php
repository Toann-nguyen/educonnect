<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConductScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->when($this->relationLoaded('student'), function () {
                return [
                    'id' => $this->student->id,
                    'student_code' => $this->student->student_code,
                    'full_name' => $this->student->user->profile->full_name,
                    'class_name' => $this->student->schoolClass->name ?? null,
                ];
            }),
            'semester' => $this->semester,
            'academic_year' => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ],
            'total_penalty_points' => $this->total_penalty_points,
            'conduct_grade' => $this->conduct_grade,
            'conduct_grade_text' => $this->getConductGradeText(),
            'teacher_comment' => $this->teacher_comment,
            'approved_by' => $this->when($this->approver, function () {
                return [
                    'id' => $this->approver->id,
                    'full_name' => $this->approver->profile->full_name,
                ];
            }),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'is_approved' => !is_null($this->approved_at),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get conduct grade in Vietnamese
     */
    private function getConductGradeText(): string
    {
        return match ($this->conduct_grade) {
            'excellent' => 'Xuất sắc',
            'good' => 'Tốt',
            'average' => 'Trung bình',
            'weak' => 'Yếu',
            default => 'Chưa xếp loại',
        };
    }
}
