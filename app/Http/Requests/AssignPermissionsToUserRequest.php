<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; 

class AssignPermissionsToUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
             /**
             * 'permissions' phải là một mảng và không được rỗng.
             * Frontend nên gửi một mảng rỗng [] nếu muốn xóa tất cả quyền khi mode='sync'.
             */
            'permissions' => 'required|array',

            /**
             * 'permissions.*' áp dụng quy tắc cho mỗi phần tử trong mảng 'permissions'.
             * Mỗi phần tử phải là một số nguyên và phải tồn tại trong cột 'id' của bảng 'permissions'.
             */
            'permissions.*' => 'integer|exists:permissions,id',

            /**
             * 'mode' là tùy chọn (sometimes), nhưng nếu được gửi,
             * giá trị của nó phải là 'sync' hoặc 'attach'.
             */
             'mode' => [
                'sometimes',
                'string',
                Rule::in(['sync', 'attach']),
            ],

        ];
    }
    public function messages(): array
    {
        return [
            'permissions.required' => 'Permissions array is required',
            'permissions.array' => 'Permissions must be an array',
            'permissions.min' => 'At least one permission is required',
            'permissions.*.exists' => 'One or more permissions do not exist'
        ];
    }
}
