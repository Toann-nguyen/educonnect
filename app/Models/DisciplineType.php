<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisciplineType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'severity_level',
        'default_penalty_points',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_penalty_points' => 'integer'
    ];

    /** Các bản ghi kỷ luật thuộc loại này */
    public function disciplines()
    {
        return $this->hasMany(Discipline::class);
    }

    /** Scope chỉ lấy các loại đang hoạt động */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Scope lọc theo mức độ nghiêm trọng */
    public function scopeBySeverity($query, $level)
    {
        return $query->where('severity_level', $level);
    }
}
