<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function getAll(array $filters)
    {
        $query = Invoice::with(['student.user.profile', 'student.schoolClass', 'feeTypes', 'issuer.profile']);

        // Filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['class_id'])) {
            $query->byClass($filters['class_id']);
        }

        if (isset($filters['is_overdue']) && $filters['is_overdue']) {
            $query->overdue();
        }

        if (isset($filters['from_date'])) {
            $query->where('due_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('due_date', '<=', $filters['to_date']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('invoice_number', 'like', "%{$filters['search']}%")
                    ->orWhere('title', 'like', "%{$filters['search']}%")
                    ->orWhereHas('student.user.profile', function ($sq) use ($filters) {
                        $sq->where('full_name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getByStudentIds(array $studentIds)
    {
        return Invoice::whereIn('student_id', $studentIds)
            ->with(['feeTypes', 'payments'])
            ->orderBy('due_date', 'desc')
            ->get();
    }

    public function getByClassId(int $classId, array $filters)
    {
        $query = Invoice::byClass($classId)
            ->with(['student.user.profile', 'feeTypes', 'payments']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('due_date', 'desc')->get();
    }

    public function create(array $data)
    {
        // Generate invoice number nếu chưa có
        if (!isset($data['invoice_number'])) {
            $data['invoice_number'] = Invoice::generateInvoiceNumber();
        }

        return Invoice::create($data);
    }

    public function update(int $invoiceId, array $data)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->update($data);
        return $invoice->load(['student.user.profile', 'feeTypes', 'payments']);
    }

    public function delete(int $invoiceId)
    {
        return Invoice::destroy($invoiceId) > 0;
    }

    public function attachFeeTypes(Invoice $invoice, array $feeTypes)
    {
        // feeTypes format: [['fee_type_id' => 1, 'amount' => 1000000, 'note' => 'optional']]
        $syncData = [];
        foreach ($feeTypes as $feeType) {
            $syncData[$feeType['fee_type_id']] = [
                'amount' => $feeType['amount'],
                'note' => $feeType['note'] ?? null,
            ];
        }
        $invoice->feeTypes()->sync($syncData);
    }

    public function getOverdueInvoices()
    {
        return Invoice::overdue()
            ->with(['student.user.profile', 'student.guardians.guardian.profile'])
            ->get();
    }

    public function getStatistics(array $filters = [])
    {
        $query = Invoice::query();

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return [
            'total_invoices' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'total_paid' => $query->sum('paid_amount'),
            'total_remaining' => $query->sum(DB::raw('total_amount - paid_amount')),
            'unpaid_count' => (clone $query)->where('status', 'unpaid')->count(),
            'partially_paid_count' => (clone $query)->where('status', 'partially_paid')->count(),
            'paid_count' => (clone $query)->where('status', 'paid')->count(),
            'overdue_count' => (clone $query)->overdue()->count(),
        ];
    }
}
