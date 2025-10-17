<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
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
            'role' => 'sometimes|required|string|exists:roles,name',
            'roles' => 'sometimes|array',
            'roles.*' => 'sometimes|required|string|exists:roles,name',
            'email' => 'sometimes|required|email|unique:users,email,' . $this->user->id,
            'profile.full_name' => 'sometimes|required|string',
            'profile.phone_number' => 'sometimes|required|string',
            'profile.birthday' => 'sometimes|required|date',
            'profile.gender' => 'sometimes|required|integer',
            'profile.address' => 'sometimes|required|string',
            'profile.avatar' => 'sometimes|nullable|string',
        ];
    }
}
