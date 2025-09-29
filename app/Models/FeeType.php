<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeType extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'name',
        'default_amount',
        'description',
        'is_active',
    ];
    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /** Các hóa đơn sử dụng loại phí này */
    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_fee_types')
            ->withPivot('amount', 'note')
            ->withTimestamps();
    }

    /** Scope lấy các loại phí đang hoạt động */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
