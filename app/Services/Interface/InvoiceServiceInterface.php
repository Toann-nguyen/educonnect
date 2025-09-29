<?php

namespace App\Services\Interface;

use \Illuminate\Database\Eloquent\Collection;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceServiceInterface
{
    public function getInvoiceForParent(User $parent);

    // Read operations
    public function getAllInvoices(array $filters, User $user): LengthAwarePaginator;
    public function getInvoiceById(int $invoiceId, User $user): Invoice;
    public function getMyInvoices(User $user);
    public function getInvoicesByClass(int $classId, array $filters, User $user);

    // Write operations
    public function createInvoice(array $data, User $creator): Invoice;
    public function updateInvoice(int $invoiceId, array $data, User $updater): Invoice;
    public function deleteInvoice(int $invoiceId, User $deleter): bool;

    // Special operations
    public function getOverdueInvoices(User $user);
    public function getStatistics(array $filters, User $user): array;

    // Permission checks
    public function canView(Invoice $invoice, User $user): bool;
    public function canManage(Invoice $invoice, User $user): bool;
}
