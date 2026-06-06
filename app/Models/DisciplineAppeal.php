<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplineAppeal extends Model
{
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

    /** Người khiếu nại */
    public function appellant()
    {
        return $this->belongsTo(User::class, 'appellant_user_id');
    }

    /** Người xem xét khiếu nại */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
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
