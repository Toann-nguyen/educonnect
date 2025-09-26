<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'period' => $this->period,
            'room' => $this->room,
            'class' => $this->whenLoaded('schoolClass', fn() => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name]),
            'subject' => $this->whenLoaded('subject', fn() => ['id' => $this->subject->id, 'name' => $this->subject->name]),
            'teacher' => $this->whenLoaded('teacher', fn() => ['id' => $this->teacher->id, 'name' => $this->teacher->profile->full_name]),
        ];
    }
}
