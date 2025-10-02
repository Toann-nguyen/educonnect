<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'payer_user_id',
        'created_by_user_id',
        'amount_paid',
        'payment_date',
        'payment_method',
        'transaction_code',
        'note'
    ];
    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'date'
    ];

    /** Lấy người tạo bản ghi thanh toán (thường là kế toán) */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
    /** Lấy hóa đơn được thanh toán */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Lấy người đã thanh toán */
    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }
}
