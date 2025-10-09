<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
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
            'score' => (float) $this->score,
            'type' => $this->type,
            'semester' => $this->semester,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Student information
            'student' => $this->when($this->relationLoaded('student'), function () {
                return [
                    'id' => $this->student->id,
                    'student_code' => $this->student->student_code,
                    'full_name' => $this->student->user?->profile?->full_name,
                    'class_name' => $this->student->schoolClass?->name,
                ];
            }),

            // Subject information
            'subject' => $this->when($this->relationLoaded('subject'), function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                ];
            }),

            // Teacher information
            'teacher' => $this->when($this->relationLoaded('teacher'), function () {
                return [
                    'id' => $this->teacher->id,
                    'full_name' => $this->teacher->profile?->full_name,
                    'email' => $this->teacher->email,
                ];
            }),
        ];
    }
}
