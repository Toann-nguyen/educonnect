<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('fee_types', 'code')->ignore($this->route('feeType'))
            ],
            'name' => 'sometimes|string|max:255',
            'default_amount' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
