<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'email'    => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed', // tự động check với password_confirmation field
                Password::min(8)
                    ->mixedCase()   // phải có chữ hoa + chữ thường
                    ->numbers()     // phải có ít nhất 1 số
                    ->uncompromised(), // kiểm tra trong danh sách password bị leak
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'              => 'Tên không được để trống.',
            'name.min'                   => 'Tên phải có ít nhất 2 ký tự.',
            'email.required'             => 'Email không được để trống.',
            'email.email'                => 'Email không hợp lệ.',
            'email.unique'               => 'Email này đã được sử dụng.',
            'password.required'          => 'Mật khẩu không được để trống.',
            'password.confirmed'         => 'Xác nhận mật khẩu không khớp.',
            'password.min'               => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.mixed_case'        => 'Mật khẩu phải có cả chữ hoa và chữ thường.',
            'password.numbers'           => 'Mật khẩu phải có ít nhất 1 chữ số.',
            'password.uncompromised'     => 'Mật khẩu này đã bị lộ trong các vụ rò rỉ dữ liệu, vui lòng chọn mật khẩu khác.',
        ];
    }

    /**
     * Chuẩn hóa dữ liệu trước khi validate
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email ?? '')),
            'name'  => trim($this->name ?? ''),
        ]);
    }
}
