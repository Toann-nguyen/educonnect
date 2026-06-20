<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'email' => $this->email,
            'name'  => $this->profile?->full_name,
            'roles' => $this->roles->pluck('name'),
        ];
    }
}
