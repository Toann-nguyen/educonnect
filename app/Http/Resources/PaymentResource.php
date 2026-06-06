<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_paid' => (float) $this->amount_paid,
            'payment_date' => $this->payment_date,
            'payment_method' => $this->payment_method,
            'transaction_code' => $this->transaction_code,
            'note' => $this->note,

            // Invoice information
            'invoice' => [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'title' => $this->invoice->title,
                'student' => [
                    'id' => $this->invoice->student->id,
                    'full_name' => $this->invoice->student->user->profile->full_name ?? 'N/A',
                    'student_code' => $this->invoice->student->student_code,
                ],
            ],

            // Payer information
            'payer' => [
                'id' => $this->payer->id,
                'full_name' => $this->payer->profile->full_name ?? $this->payer->email,
                'email' => $this->payer->email,
            ],

            // Creator information
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'full_name' => $this->creator->profile->full_name ?? $this->creator->email,
            ] : null,

            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
