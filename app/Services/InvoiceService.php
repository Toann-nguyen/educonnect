<?php

namespace App\Services;

use App\Services\Interface\InvoiceServiceInterface;
use \App\Models\User;
use App\Models\Invoice;
use \Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;

class InvoiceService implements InvoiceServiceInterface
{
    protected $invoiceRepository;

    public function __construct(InvoiceRepositoryInterface $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }

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

    public function getAllInvoices(array $filters, User $user): LengthAwarePaginator
    {
        // Áp dụng logic phân quyền
        if (!$user->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to view all invoices.');
        }
        dd($user->all());

        return $this->invoiceRepository->getAll($filters);
    }

    public function getInvoiceById(int $invoiceId, User $user): Invoice
    {
        $invoice = Invoice::with(['student.user.profile', 'feeTypes', 'payments.payer.profile'])
            ->findOrFail($invoiceId);

        if (!$this->canView($invoice, $user)) {
            throw new AuthorizationException('You are not authorized to view this invoice.');
        }


        return $invoice;
    }

    public function getMyInvoices(User $user)
    {
        // Student: xem hóa đơn của bản thân
        if ($user->hasRole('student') && $user->student) {
            return $this->invoiceRepository->getByStudentIds([$user->student->id]);
        }

        // Parent: xem hóa đơn của các con
        if ($user->hasRole('parent')) {
            $childrenIds = $user->guardianStudents()->pluck('students.id')->toArray();
            return $this->invoiceRepository->getByStudentIds($childrenIds);
        }

        // Homeroom teacher: xem hóa đơn của lớp chủ nhiệm
        if ($user->hasRole('teacher')) {
            $homeroomClass = $user->homeroomClasses()->first();
            if ($homeroomClass) {
                return $this->invoiceRepository->getByClassId($homeroomClass->id, []);
            }
        }

        return collect();
    }

    public function getInvoicesByClass(int $classId, array $filters, User $user)
    {
        // Kiểm tra quyền xem theo lớp
        $canView = match (true) {
            $user->hasRole(['admin', 'principal', 'accountant']) => true,
            $user->hasRole('teacher') && $user->homeroomClasses()->where('id', $classId)->exists() => true,
            default => false
        };

        if (!$canView) {
            throw new AuthorizationException('You are not authorized to view invoices for this class.');
        }

        return $this->invoiceRepository->getByClassId($classId, $filters);
    }

    public function createInvoice(array $data, User $creator): Invoice
    {
        // Chỉ admin, principal, accountant mới được tạo hóa đơn
        if (!$creator->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to create invoices.');
        }

        DB::beginTransaction();
        try {
            // Thêm issued_by
            $data['issued_by'] = $creator->id;


            // Tính total_amount từ fee_types nếu có
            if (isset($data['fee_types']) && is_array($data['fee_types'])) {
                $totalAmount = array_sum(array_column($data['fee_types'], 'amount'));
                $data['total_amount'] = $totalAmount;
                $data['amount'] = $totalAmount; // Backward compatibility
            }

            // Đặt paid_amount mặc định = 0
            $data['paid_amount'] = 0;
            $data['status'] = 'unpaid';

            // Tạo invoice
            $invoice = $this->invoiceRepository->create($data);

            // Gắn fee types nếu có
            if (isset($data['fee_types']) && is_array($data['fee_types'])) {
                $this->invoiceRepository->attachFeeTypes($invoice, $data['fee_types']);
            }

            DB::commit();
            Log::info('Invoice created successfully.', ['invoice_id' => $invoice->id, 'creator' => $creator->id]);

            return $invoice->load(['student.user.profile', 'feeTypes', 'issuer.profile']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create invoice.', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    public function updateInvoice(int $invoiceId, array $data, User $updater): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if (!$this->canManage($invoice, $updater)) {
            throw new AuthorizationException('You do not have permission to update this invoice.');
        }

        DB::beginTransaction();
        try {
            // Cập nhật total_amount nếu có fee_types mới
            if (isset($data['fee_types']) && is_array($data['fee_types'])) {
                $totalAmount = array_sum(array_column($data['fee_types'], 'amount'));
                $data['total_amount'] = $totalAmount;
                $data['amount'] = $totalAmount;
            }

            // Cập nhật invoice
            $invoice = $this->invoiceRepository->update($invoiceId, $data);

            // Cập nhật fee types nếu có
            if (isset($data['fee_types']) && is_array($data['fee_types'])) {
                $this->invoiceRepository->attachFeeTypes($invoice, $data['fee_types']);
            }

            DB::commit();
            Log::info('Invoice updated successfully.', ['invoice_id' => $invoiceId, 'updater' => $updater->id]);

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update invoice.', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function deleteInvoice(int $invoiceId, User $deleter): bool
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if (!$this->canManage($invoice, $deleter)) {
            throw new AuthorizationException('You do not have permission to delete this invoice.');
        }

        // Không được xóa hóa đơn đã có thanh toán
        if ($invoice->paid_amount > 0) {
            throw new \Exception('Cannot delete invoice with existing payments.');
        }

        DB::beginTransaction();
        try {
            $result = $this->invoiceRepository->delete($invoiceId);
            DB::commit();
            Log::info('Invoice deleted successfully.', ['invoice_id' => $invoiceId, 'deleter' => $deleter->id]);
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete invoice.', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getOverdueInvoices(User $user)
    {
        // Chỉ admin, principal, accountant mới xem được tất cả hóa đơn quá hạn
        if (!$user->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to view overdue invoices.');
        }

        return $this->invoiceRepository->getOverdueInvoices();
    }

    public function getStatistics(array $filters, User $user): array
    {
        // Chỉ admin, principal, accountant mới xem thống kê
        if (!$user->hasRole(['admin', 'principal', 'accountant'])) {
            throw new AuthorizationException('You do not have permission to view statistics.');
        }

        return $this->invoiceRepository->getStatistics($filters);
    }

    public function canView(Invoice $invoice, User $user): bool
    {
        return match (true) {
            // Admin, Principal, Accountant: xem tất cả
            $user->hasRole(['admin', 'principal', 'accountant']) => true,

            // Homeroom teacher: xem hóa đơn của học sinh trong lớp chủ nhiệm
            $user->hasRole('teacher') && $user->homeroomClasses()
                ->where('id', $invoice->student->class_id)->exists() => true,

            // Student: chỉ xem hóa đơn của chính mình
            $user->hasRole('student') && $user->student?->id === $invoice->student_id => true,

            // Parent: xem hóa đơn của con
            $user->hasRole('parent') && $user->guardianStudents()
                ->where('students.id', $invoice->student_id)->exists() => true,

            default => false
        };
    }

    public function canManage(Invoice $invoice, User $user): bool
    {
        // Chỉ admin, principal, accountant mới được quản lý (tạo/sửa/xóa)
        return $user->hasRole(['admin', 'principal', 'accountant']);
    }
}
