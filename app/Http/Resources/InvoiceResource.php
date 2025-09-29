<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'title' => $this->title,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'due_date' => $this->due_date->format('Y-m-d'),
            'status' => $this->status,
            'is_overdue' => $this->is_overdue,
            'note' => $this->note,

            // Student information
            'student' => [
                'id' => $this->student->id,
                'student_code' => $this->student->student_code,
                'full_name' => $this->student->user->profile->full_name ?? 'N/A',
                'class' => [
                    'id' => $this->student->schoolClass->id ?? null,
                    'name' => $this->student->schoolClass->name ?? 'N/A',
                ],
            ],

            // Fee types
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
            'issued_by' => $this->issuer ? [
                'id' => $this->issuer->id,
                'full_name' => $this->issuer->profile->full_name ?? $this->issuer->email,
            ] : null,

            // Payment summary
            'payment_count' => $this->payments->count(),
            'last_payment_date' => $this->payments->first()?->payment_date,

            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
