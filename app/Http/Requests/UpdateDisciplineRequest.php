<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisciplineRequest extends FormRequest
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
            'discipline_type_id' => 'sometimes|integer|exists:discipline_types,id',
            'incident_date' => 'sometimes|date|before_or_equal:today',
            'incident_location' => 'nullable|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'penalty_points' => 'nullable|integer|min:0|max:50',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|string',
        ];
    }
    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'discipline_type_id.exists' => 'Loại vi phạm không tồn tại',
            'incident_date.before_or_equal' => 'Ngày xảy ra không được sau hôm nay',
            'description.max' => 'Mô tả không được vượt quá 2000 ký tự',
        ];
    }
}
