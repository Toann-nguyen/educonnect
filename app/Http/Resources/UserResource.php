<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'profile' => [
                'full_name' => $this->profile?->full_name,
                'phone_number' => $this->profile?->phone_number,
                'birthday' => $this->profile?->birthday,
                'gender' => $this->profile?->gender,
                'address' => $this->profile?->address,
                'avatar' => $this->profile?->avatar,
            ],
            'roles' => $this->roles->pluck('name'),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
