<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize()
    {
        // return $this->user()->can('manage_roles');
        return true;
    }

    public function rules()
    {
        return [
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }
}
