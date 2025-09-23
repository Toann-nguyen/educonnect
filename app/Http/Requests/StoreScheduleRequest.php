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
            'class_id' => 'required|integer|exists:classes,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:users,id',
            'day_of_week' => 'required|integer|between:2,8', // 2: Monday, 8: Sunday
            'period' => 'required|integer|min:1|max:16', // Tiết học
            'room' => 'nullable|string|max:255',
        ];
    }
}
