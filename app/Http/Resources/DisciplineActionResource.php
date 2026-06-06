<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplineActionResource extends JsonResource
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
            'action_type' => $this->action_type,
            'action_description' => $this->action_description,
            'executed_by' => $this->when($this->executor, function () {
                return [
                    'id' => $this->executor->id,
                    'full_name' => $this->executor->profile->full_name,
                ];
            }),
            'executed_at' => $this->executed_at?->format('Y-m-d H:i:s'),
            'completion_status' => $this->completion_status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
