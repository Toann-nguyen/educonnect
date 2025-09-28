<?php

namespace App\Services;

use App\Services\Interface\InvoiceServiceInterface;
use \App\Models\User;
use \Illuminate\Database\Eloquent\Collection;

class InvoiceService implements InvoiceServiceInterface
{
    public function getInvoiceForParent(User $parent)
    {
        // Lấy danh sách ID của các con
        $childrenIds = $parent->guardianStudents()->pluck('students.id')->toArray();

        if (empty($childrenIds)) {
            return new Collection();
        }

        // Lấy hóa đơn dựa trên danh sách ID đó
        return $this->invoiceRepository->getByStudentIds($childrenIds);
    }
}
