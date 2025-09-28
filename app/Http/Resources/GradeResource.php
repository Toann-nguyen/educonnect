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
            'subject' => $this->whenLoaded('subject', $this->subject->name),
        ];
    }
}
