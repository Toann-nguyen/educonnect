<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplineAppealResource extends JsonResource
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
            'appellant' => [
                'id' => $this->appellant->id,
                'full_name' => $this->appellant->profile->full_name,
                'type' => $this->appellant_type,
            ],
            'appeal_reason' => $this->appeal_reason,
            'evidence' => $this->evidence,
            'status' => $this->status,
            'reviewed_by' => $this->when($this->reviewer, function () {
                return [
                    'id' => $this->reviewer->id,
                    'full_name' => $this->reviewer->profile->full_name,
                ];
            }),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'review_response' => $this->review_response,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
