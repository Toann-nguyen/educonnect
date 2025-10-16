<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'name' => $this->name,
            
            'is_active' => $this->is_active,

            // Dữ liệu permissions sẽ được lấy từ mối quan hệ đã được load
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            
        ];
    }
}
