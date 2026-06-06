<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'invoice_id',
        'fee_type_id',
        'description',
        'unit_price',
        'quantity',
        'total_amount',
        'note'
    ];

    // realtionships


    /**
     * Mục này thuộc về loại phí nào.
     */
    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }


    // end realationships
}
