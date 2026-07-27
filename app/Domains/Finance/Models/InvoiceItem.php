<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $connection = 'finance';

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'fee_type_id',
        'description',
        'unit_price',
        'quantity',
        'total_amount',
        'note',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'total_amount' => 'decimal:2',
    ];
}