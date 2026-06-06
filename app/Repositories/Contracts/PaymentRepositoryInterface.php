<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PaymentRepositoryInterface
{
    public function getAll(array $filters): LengthAwarePaginator;
    public function getByInvoiceId(int $invoiceId): Collection;
    public function create(array $data): Payment;
    public function delete(int $paymentId): bool;
    public function getStatistics(array $filters = []): array;
}
