<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        return [ // full_name là bắt buộc, phải là chuỗi, tối đa 255 ký tự
            'full_name' => 'required|string|max:255',

            // email là bắt buộc, phải là định dạng email hợp lệ, và phải là duy nhất (unique) trong bảng 'users'
            'email' => 'required|string|email|max:255|unique:users,email',

            // password là bắt buộc, phải là chuỗi, tối thiểu 8 ký tự, và phải khớp với trường 'password_confirmation'
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
