<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppealDisciplineRequest extends FormRequest
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
            'appeal_reason' => 'required|string|min:20|max:2000',
            'evidence' => 'nullable|array',
            'evidence.*' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'appeal_reason.required' => 'Vui lòng nêu rõ lý do khiếu nại',
            'appeal_reason.min' => 'Lý do khiếu nại phải có ít nhất 20 ký tự',
            'appeal_reason.max' => 'Lý do khiếu nại không được vượt quá 2000 ký tự',
        ];
    }
}
