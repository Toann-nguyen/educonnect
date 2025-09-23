<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
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
            'user_id' => 'sometimes|integer|exists:users,id|unique:students,user_id',
            'class_id' => 'sometimes|integer|exists:classes,id',
            'student_code' => 'sometimes|string|max:255|unique:students,student_code',
            'status' => 'sometimes|string',
        ];
    }
}
