<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'title' => $this->title,
            'notes' => $this->notes,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) ($this->total_amount - $this->paid_amount),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status,
            'is_overdue' => $this->is_overdue ?? false,

            // Student information
            'student' => [
                'id' => $this->student?->id,
                'student_code' => $this->student?->student_code,
                'full_name' => $this->student?->user?->profile?->full_name,
                'email' => $this->student?->user?->email,
                'class' => [
                    'id' => $this->student?->schoolClass?->id,
                    'name' => $this->student?->schoolClass?->name,
                ],
                'guardians' => $this->student?->guardians->map(function ($guardian) {
                    return [
                        'id' => $guardian->guardian?->id,
                        'full_name' => $guardian->guardian?->profile?->full_name,
                        'phone' => $guardian->guardian?->profile?->phone_number,
                        'email' => $guardian->guardian?->email,
                        'relationship' => $guardian->relationship,
                    ];
                }) ?? [],
            ],

            // Fee types breakdown (array format for frontend)
            'fee_types' => $this->feeTypes->map(function ($feeType) {
                return [
                    'id' => $feeType->id,
                    'code' => $feeType->code,
                    'name' => $feeType->name,
                    'amount' => (float) $feeType->pivot->amount,
                    'note' => $feeType->pivot->note,
                ];
            }),

            // Issuer information
            'issuer' => [
                'id' => $this->issuer?->id,
                'full_name' => $this->issuer?->profile?->full_name,
                'email' => $this->issuer?->email,
            ],

            // Payment history
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount_paid' => (float) $payment->amount_paid,
                        'payment_date' => $payment->payment_date?->format('Y-m-d'),
                        'payment_method' => $payment->payment_method,
                        'transaction_code' => $payment->transaction_code,
                        'payer' => [
                            'id' => $payment->payer?->id,
                            'full_name' => $payment->payer?->profile?->full_name,
                        ],
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
