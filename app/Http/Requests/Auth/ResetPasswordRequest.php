<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            // token là bắt buộc (nhận được từ email)
            'token' => 'required|string',

            // email là bắt buộc và phải tồn tại
            'email' => 'required|string|email|exists:users,email',

            // password là bắt buộc, tối thiểu 8 ký tự và phải khớp với password_confirmation
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
