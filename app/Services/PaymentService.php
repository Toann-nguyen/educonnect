<?php

namespace App\Services;

use App\Services\Interface\PaymentServiceInterface;
use App\Models\User;
use App\Models\Payment;
use App\Models\Invoice;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentService implements PaymentServiceInterface
{
    protected $paymentRepository;

    public function __construct(PaymentRepositoryInterface $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    public function getAllPayments(array $filters, User $user): LengthAwarePaginator
    {
        // Chỉ admin, principal, accountant mới xem tất cả
        if (!$user->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to view all payments.');
        }

        return $this->paymentRepository->getAll($filters);
    }

    public function getPaymentsByInvoice(int $invoiceId, User $user)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        // Kiểm tra quyền xem invoice
        $canView = match (true) {
            $user->hasRole(['admin', 'principal', 'accountant']) => true,
            $user->hasRole('teacher') && $user->homeroomClasses()
                ->where('id', $invoice->student->class_id)->exists() => true,
            $user->hasRole('student') && $user->student?->id === $invoice->student_id => true,
            $user->hasRole('parent') && $user->guardianStudents()
                ->where('students.id', $invoice->student_id)->exists() => true,
            default => false
        };

        if (!$canView) {
            throw new AuthorizationException('You are not authorized to view payments for this invoice.');
        }

        return $this->paymentRepository->getByInvoiceId($invoiceId);
    }

    public function createPayment(array $data, User $creator): Payment
    {
        // Kiểm tra quyền tạo payment
        // Parent có thể tạo payment cho con (payer_user_id = parent_id)
        // Accountant/Admin có thể tạo payment cho bất kỳ ai
        // dd($data['payer_user_id'], $creator->id, $data);

        $canCreate = match (true) {
            $creator->hasRole(['admin', 'principal', 'accountant']) => true,
            $creator->hasRole('parent') && isset($data['payer_user_id'])
                && $data['payer_user_id'] == $creator->id => true,
            default => false
        };

        if (!$canCreate) {
            throw new AuthorizationException('You do not have permission to create payments.');
        }

        DB::beginTransaction();
        try {
            // Lấy invoice
            $invoice = Invoice::findOrFail($data['invoice_id']);

            // Kiểm tra số tiền thanh toán
            $amountPaid = $data['amount_paid'];
            if ($amountPaid <= 0) {
                throw new \Exception('Payment amount must be greater than zero.');
            }

            $remainingAmount = $invoice->total_amount - $invoice->paid_amount;
            // ✅ FIX: Check xem hóa đơn đã paid hết chưa
            if ($remainingAmount <= 0) {
                throw new \Exception(
                    "Invoice is already fully paid. Remaining amount: {$remainingAmount}",
                    422
                );
            }

            // Thêm created_by_user_id
            $data['created_by_user_id'] = $creator->id;

            // Đặt payment_date mặc định là hôm nay nếu chưa có
            if (!isset($data['payment_date'])) {
                $data['payment_date'] = now();
            }

            // Tạo payment
            $payment = $this->paymentRepository->create($data);

            // Cập nhật paid_amount và status của invoice
            $invoice->paid_amount += $amountPaid;
            $invoice->updatePaymentStatus();

            DB::commit();
            Log::info('Payment created successfully.', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amountPaid,
                'creator' => $creator->id
            ]);

            return $payment->load(['invoice.student.user.profile', 'payer.profile', 'creator.profile']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payment.', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    public function deletePayment(int $paymentId, User $deleter): bool
    {
        // Chỉ admin, accountant mới được xóa payment
        if (!$deleter->hasRole(['admin', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to delete payments.');
        }

        DB::beginTransaction();
        try {
            $payment = Payment::findOrFail($paymentId);
            $invoice = $payment->invoice;

            // Trừ lại paid_amount của invoice
            $invoice->paid_amount -= $payment->amount_paid;
            $invoice->updatePaymentStatus();

            // Xóa payment
            $result = $this->paymentRepository->delete($paymentId);

            DB::commit();
            Log::info('Payment deleted successfully.', ['payment_id' => $paymentId, 'deleter' => $deleter->id]);

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete payment.', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getStatistics(array $filters, User $user): array
    {
        // Chỉ admin, principal, accountant mới xem thống kê
        if (!$user->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to view statistics.');
        }

        return $this->paymentRepository->getStatistics($filters);
    }
}
