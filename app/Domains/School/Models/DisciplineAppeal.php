<?php

namespace App\Domains\School\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplineAppeal extends Model
{
    protected $connection = 'school';
    use HasFactory;
    protected $fillable = [
        'discipline_id',
        'appellant_user_id',
        'appellant_type',
        'appeal_reason',
        'evidence',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_response'
    ];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime'
    ];

    /** Bản ghi kỷ luật */
    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    /** Scope lấy các khiếu nại chưa xem xét */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Scope lọc theo loại người khiếu nại */
    public function scopeByAppellantType($query, $type)
    {
        return $query->where('appellant_type', $type);
    }
}
