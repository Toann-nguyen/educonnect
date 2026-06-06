<?php

namespace App\Repositories\Contracts;

use  \Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;

interface InvoiceRepositoryInterface
{
    public function getAll(array $filters);
    public function getByStudentIds(array $studentIds);
    public function getByClassId(int $classId, array $filters);
    public function create(array $data);
    public function update(int $invoiceId, array $data);
    public function delete(int $invoiceId);
    public function attachFeeTypes(Invoice $invoice, array $feeTypes);
    public function getOverdueInvoices();
    public function getStatistics(array $filters = []);
}
