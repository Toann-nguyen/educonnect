<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'status' => ['sometimes', 'string', Rule::in(['unpaid', 'paid', 'partially_paid', 'overdue'])],
        ];
    }
}
