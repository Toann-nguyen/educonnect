<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'student_id',
        'title',
        'amount', // Giữ để backward compatibility
        'total_amount',
        'paid_amount',
        'due_date',
        'status',
        'note',
        'issued_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date'
    ];
    protected $appends = ['remaining_amount', 'is_overdue'];

    //  Realationships

    /**
     * Một hóa đơn có nhiều mục chi tiết.
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** Lấy thông tin học sinh của hóa đơn */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Lấy lịch sử thanh toán */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    /** Người tạo hóa đơn */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Các loại phí trong hóa đơn */
    public function feeTypes()
    {
        return $this->belongsToMany(FeeType::class, 'invoice_fee_types')
            ->withPivot('amount', 'note')
            ->withTimestamps();
    }


    /** Tính số tiền còn lại */
    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    /** Kiểm tra có quá hạn không */
    public function getIsOverdueAttribute()
    {
        return $this->due_date < now()->format('Y-m-d') &&
            in_array($this->status, ['unpaid', 'partially_paid']);
    }
    // end relationships
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

    /** Cập nhật trạng thái thanh toán */
    public function updatePaymentStatus()
    {
        if ($this->paid_amount >= $this->total_amount) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partially_paid';
        } else {
            $this->status = 'unpaid';
        }
        $this->save();
    }
    /**
     * Generate a unique invoice number
     * Format: INV-YYYYMM-XXXX where XXXX is a sequential number
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ym') . '-';

        // Get the last invoice number for this month
        $lastInvoice = self::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if (!$lastInvoice) {
            // If no invoice exists for this month, start with 0001
            $nextNumber = '0001';
        } else {
            // Extract the numeric part and increment it
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix . $nextNumber;
    }
}
