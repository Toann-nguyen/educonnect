<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisciplineRequest extends FormRequest
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
            'student_id' => 'required|integer|exists:students,id',
            'discipline_type_id' => 'required|integer|exists:discipline_types,id',
            'incident_date' => 'required|date|before_or_equal:today',
            'incident_location' => 'nullable|string|max:255',
            'description' => 'required|string|max:2000',
            'penalty_points' => 'nullable|integer|min:0|max:50',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|string',
        ];
    }
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-set reporter_user_id to current user
        $this->merge([
            'reporter_user_id' => $this->user()->id,
            'status' => 'pending', // Always start as pending
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Vui lòng chọn học sinh',
            'student_id.exists' => 'Học sinh không tồn tại',
            'discipline_type_id.required' => 'Vui lòng chọn loại vi phạm',
            'discipline_type_id.exists' => 'Loại vi phạm không tồn tại',
            'incident_date.required' => 'Vui lòng nhập ngày xảy ra sự việc',
            'incident_date.before_or_equal' => 'Ngày xảy ra không được sau hôm nay',
            'description.required' => 'Vui lòng mô tả chi tiết sự việc',
            'description.max' => 'Mô tả không được vượt quá 2000 ký tự',
        ];
    }
}
