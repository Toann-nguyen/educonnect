<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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
            'schedule_id' => 'required|integer|exists:schedules,id',
            'date' => 'required|date',
            'status' => ['required', 'string', Rule::in(['present', 'absent', 'late'])],
            'note' => 'nullable|string|max:500',
        ];
    }
}
