<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [
                'full_name'    => null,
                'phone_number' => null,
                'birthday'     => null,
                'gender'       => null,
                'address'      => null,
                'avatar'       => null,
            ];
        }

        return [
            'full_name'    => $this->full_name,
            'phone_number' => $this->phone_number,
            'birthday'     => $this->birthday,
            'gender'       => $this->gender,
            'address'      => $this->address,
            'avatar'       => $this->avatar,
        ];
    }
}
