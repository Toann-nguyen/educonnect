<?php

namespace App\Services\Interface;

use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;


interface PaymentServiceInterface
{
    public function getAllPayments(array $filters, User $user): LengthAwarePaginator;
    public function getPaymentsByInvoice(int $invoiceId, User $user);
    public function createPayment(array $data, User $creator): Payment;
    public function deletePayment(int $paymentId, User $deleter): bool;
    public function getStatistics(array $filters, User $user): array;
}
