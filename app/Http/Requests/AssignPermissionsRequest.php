<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
      }

    public function rules()
    {
        return [
            'permissions' => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id',
            'mode' => 'sometimes|string|in:sync,attach',
        ];
    }

    public function messages()
    {
        return [
            'permissions.required' => 'Mảng permissions là bắt buộc.',
            'permissions.array' => 'Permissions phải là một mảng.',
            'permissions.*.integer' => 'Mỗi permission ID phải là một số nguyên.',
            'permissions.*.exists' => 'Một hoặc nhiều permission ID không tồn tại.',
        ];
    }
}
