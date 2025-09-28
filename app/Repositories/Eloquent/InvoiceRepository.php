<?php

namespace App\Repositories\Eloquent;

use \App\Models\Invoice;

use App\Repositories\Contracts\InvoiceRepositoryInterface;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function getByStudentIds(array $studentIds)
    {
        return Invoice::whereIn('student_id', $studentIds)
            ->orderBy('due_date', 'desc')
            ->get();
    }
}
