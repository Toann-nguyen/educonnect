<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRolesToUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    public function rules()
    {
        return [
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
            'mode' => 'in:add,replace,sync',
        ];
    }

    public function messages()
    {
        return [
            'roles.required' => 'At least one role is required',
            'roles.*.exists' => 'One or more roles do not exist',
        ];
    }
}
