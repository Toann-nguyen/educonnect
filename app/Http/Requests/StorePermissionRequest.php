<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:permissions,name',
                'regex:/^[a-z_]+$/' // Only lowercase letters and underscores
            ],
            'guard_name' => 'nullable|string|max:50|in:web,api'

        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Permission name is required',
            'name.unique' => 'This permission name already exists',
            'name.regex' => 'Permission name must be in snake_case format (lowercase with underscores only)',
            'name.max' => 'Permission name cannot exceed 100 characters',
            'guard_name.in' => 'Guard name must be either "web" or "api"'
        ];
    }
    
}
