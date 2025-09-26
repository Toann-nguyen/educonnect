<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('schedule'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'class_id' => 'sometimes|required|integer|exists:school_classes,id',
            'subject_id' => 'sometimes|required|integer|exists:subjects,id',
            'teacher_id' => 'sometimes|required|integer|exists:users,id',
            'day_of_week' => 'sometimes|required|integer|between:1,7', // 1: Monday, 7: Sunday
            'period' => 'sometimes|required|integer|between:1,10', // Tiết học từ 1-10
            'room' => 'sometimes|required|string|max:10',
            'date' => 'sometimes|required|date',
            'academic_year_id' => 'sometimes|required|exists:academic_years,id',
            'semester' => 'sometimes|required|integer|in:1,2',
        ];
    }
}
