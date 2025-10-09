<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('manage_roles') && $this->user()->can('manage_permissions');
    }

    public function rules()
    {
        return [
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'integer|exists:permissions,id',
            'mode' => 'in:add,replace,sync',
        ];
    }

    public function messages()
    {
        return [
            'permissions.required' => 'At least one permission is required',
            'permissions.*.exists' => 'One or more permissions do not exist',
        ];
    }
}
