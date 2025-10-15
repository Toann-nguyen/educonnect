<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class StoreRoleRequest extends FormRequest
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
        // Lấy danh sách tất cả các guard đã được định nghĩa trong config/auth.php
        $availableGuards = array_keys(config('auth.guards'));

        return [

            'guard_name' => [
                'sometimes', // Không bắt buộc, nếu không gửi sẽ dùng giá trị mặc định
                'string',
                Rule::in($availableGuards), // Giá trị phải nằm trong danh sách các guard hợp lệ
            ],

            'name' => 'required|string|max:255|unique:roles,name',

            'description' => 'nullable|string',

            'is_active' => 'sometimes|boolean',

            'permissions' => 'nullable|array',

            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            // ... (các message khác)
            'guard_name.in' => 'The selected guard name is invalid.',
        ];
    }

    /**
     * Ghi đè phương thức xử lý khi validation thất bại.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $responsePayload = [
            'message' => 'Data khong hop le hoac da ton tai',
            'errors' => $errors,
        ];

        // KIỂM TRA XEM LỖI CÓ PHẢI LÀ DO 'guard_name' HAY KHÔNG
        if ($errors->has('guard_name')) {
            // Nếu có, thêm danh sách các guard hợp lệ vào response
            $responsePayload['available_guards'] = array_keys(config('auth.guards'));
        }

        // Ném ra một exception chứa response JSON đã được tùy chỉnh
        throw new HttpResponseException(response()->json($responsePayload, 422));
    }
}
