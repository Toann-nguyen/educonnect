<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeeTypeRequest extends FormRequest
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
            'code' => 'required|string|max:20|unique:fee_types,code',
            'name' => 'required|string|max:255',
            'default_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Fee type code is required.',
            'code.unique' => 'This fee type code already exists.',
            'name.required' => 'Fee type name is required.',
            'default_amount.required' => 'Default amount is required.',
            'default_amount.min' => 'Default amount must be at least 0.',
        ];
    }
}
