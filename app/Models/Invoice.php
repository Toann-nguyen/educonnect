<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'student_id',
        'issued_by',
        'title',
        'notes',
        'total_amount',
        'paid_amount',
        'due_date',
        'status',
        'amount' // Backward compatibility
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'due_date' => 'date'
    ];

    protected $appends = ['is_overdue', 'remaining_amount'];

    /** Học sinh nhận hóa đơn */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    /**
     * Các mục chi tiết trong hóa đơn.
     * Đây là mối quan hệ Một-Nhiều (One-to-Many).
     * ĐÂY LÀ PHƯƠNG THỨC BẠN CÒN THIẾU.
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** Người phát hành hóa đơn (kế toán/admin) */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Các loại phí trong hóa đơn (Many-to-Many) */
    public function feeTypes()
    {
        return $this->belongsToMany(FeeType::class, 'invoice_fee_types')
            ->withPivot('amount', 'note')
            ->withTimestamps();
    }

    /** Các thanh toán cho hóa đơn này */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /** Tạo mã hóa đơn tự động */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    /** Cập nhật trạng thái thanh toán dựa trên paid_amount */
    public function updatePaymentStatus(): void
    {
        if ($this->paid_amount <= 0) {
            $this->status = 'unpaid';
        } elseif ($this->paid_amount >= $this->total_amount) {
            $this->status = 'paid';
        } else {
            $this->status = 'partially_paid';
        }

        $this->save();
    }

    /** Scope lọc hóa đơn quá hạn */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->format('Y-m-d'))
            ->whereIn('status', ['unpaid', 'partially_paid']);
    }

    /** Scope lọc theo lớp học */
    public function scopeByClass($query, $classId)
    {
        return $query->whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });
    }

    /** Scope lọc theo trạng thái */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /** Accessor: Kiểm tra có quá hạn không */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date < now()->startOfDay() &&
            in_array($this->status, ['unpaid', 'partially_paid']);
    }

    /** Accessor: Số tiền còn lại */
    public function getRemainingAmountAttribute(): float
    {
        return (float) ($this->total_amount - $this->paid_amount);
    }
}
