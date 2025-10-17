<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserByAdminRequest extends FormRequest
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
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',

            // Mật khẩu là tùy chọn, nếu không gửi sẽ được tạo tự động
            'password' => 'nullable|string|min:8',

            // Roles phải là một mảng và phải tồn tại trong bảng roles
            'roles' => 'required|array|min:1',
            'roles.*' => 'integer|exists:roles,id', 
        ];
    }
}
