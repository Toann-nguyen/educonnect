<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'student_code' => $this->student_code,
            // Lấy thông tin từ các mối quan hệ đã được eager load
            'full_name' => $this->whenLoaded('user', $this->user->profile?->full_name),
            'class_name' => $this->whenLoaded('schoolClass', $this->schoolClass?->name),
        ];
    }
}
