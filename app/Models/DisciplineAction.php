<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplineAction extends Model
{
    use HasFactory;
    protected $fillable = [
        'discipline_id',
        'action_type',
        'action_description',
        'executed_by_user_id',
        'executed_at',
        'completion_status'
    ];

    protected $casts = [
        'executed_at' => 'datetime'
    ];

    /** Bản ghi kỷ luật */
    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    /** Người thực hiện */
    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    /** Scope lọc theo loại hành động */
    public function scopeByActionType($query, $type)
    {
        return $query->where('action_type', $type);
    }

    /** Scope lấy các hành động đã hoàn thành */
    public function scopeCompleted($query)
    {
        return $query->where('completion_status', 'completed');
    }

    /** Scope lấy các hành động đang chờ */
    public function scopeScheduled($query)
    {
        return $query->where('completion_status', 'scheduled');
    }
}
