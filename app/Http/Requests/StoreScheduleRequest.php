<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
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
            'class_id' => 'required|integer|exists:school_classes,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:users,id',
            'day_of_week' => 'required|integer|between:1,7', // 1: Monday, 7: Sunday
            'period' => 'required|integer|between:1,10', // Tiết học từ 1-10
            'room' => 'required|string|max:10',
            'date' => 'required|date',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester' => 'required|integer|in:1,2',
        ];
    }
}
