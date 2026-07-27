<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $connection = 'finance';

    protected $table = 'payments';

    protected $fillable = [
        'invoice_id',
        'payer_user_id',
        'created_by_user_id',
        'amount_paid',
        'payment_date',
        'payment_method',
        'transaction_code',
        'note',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'date',
    ];
}