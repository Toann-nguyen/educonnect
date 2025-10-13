<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Payment::with([
            'invoice.student.user.profile',
            'payer.profile',
            'creator.profile'
        ]);

        if (isset($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        if (isset($filters['payer_user_id'])) {
            $query->where('payer_user_id', $filters['payer_user_id']);
        }

        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['from_date'])) {
            $query->where('payment_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('payment_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('payment_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getByInvoiceId(int $invoiceId): Collection
    {
        return Payment::where('invoice_id', $invoiceId)
            ->with(['payer.profile', 'creator.profile'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function delete(int $paymentId): bool
    {
        return Payment::destroy($paymentId) > 0;
    }

    public function getStatistics(array $filters = []): array
    {
        $query = Payment::query();

        if (isset($filters['from_date'])) {
            $query->where('payment_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('payment_date', '<=', $filters['to_date']);
        }

        return [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount_paid'),
            'cash_amount' => (clone $query)->where('payment_method', 'cash')->sum('amount_paid'),
            'banking_amount' => (clone $query)->where('payment_method', 'banking')->sum('amount_paid'),
            'by_method' => (clone $query)->groupBy('payment_method')
                ->selectRaw('payment_method, count(*) as count, sum(amount_paid) as total')
                ->get()
        ];
    }
}