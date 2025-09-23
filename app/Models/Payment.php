<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

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
