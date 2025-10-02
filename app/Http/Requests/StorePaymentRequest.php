<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'invoice_id' => 'required|integer|exists:invoices,id',
            'payer_user_id' => 'required|integer|exists:users,id',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => ['required', 'string', Rule::in(['cash', 'banking'])],
            'transaction_code' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ];
    }
}
