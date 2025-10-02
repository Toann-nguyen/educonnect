<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplineResource extends JsonResource
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
            'student' => [
                'id' => $this->student->id,
                'student_code' => $this->student->student_code,
                'full_name' => $this->student->user->profile->full_name,
                'class_name' => $this->student->schoolClass->name ?? null,
            ],
            'discipline_type' => [
                'id' => $this->disciplineType->id,
                'code' => $this->disciplineType->code,
                'name' => $this->disciplineType->name,
                'severity_level' => $this->disciplineType->severity_level,
                'default_penalty_points' => $this->disciplineType->default_penalty_points,
            ],
            'reporter' => [
                'id' => $this->reporter->id,
                'full_name' => $this->reporter->profile->full_name,
                'role' => $this->reporter->roles->pluck('name')->first(),
            ],
            'incident_date' => $this->incident_date->format('Y-m-d'),
            'incident_location' => $this->incident_location,
            'description' => $this->description,
            'penalty_points' => $this->penalty_points,
            'status' => $this->status,
            'reviewed_by' => $this->when($this->reviewer, function () {
                return [
                    'id' => $this->reviewer->id,
                    'full_name' => $this->reviewer->profile->full_name,
                ];
            }),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'review_note' => $this->review_note,
            'parent_notified' => $this->parent_notified,
            'parent_notified_at' => $this->parent_notified_at?->format('Y-m-d H:i:s'),
            'attachments' => $this->attachments,
            'actions' => DisciplineActionResource::collection($this->whenLoaded('actions')),
            'appeals' => DisciplineAppealResource::collection($this->whenLoaded('appeals')),
            'has_appeals' => $this->hasAppeals(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
