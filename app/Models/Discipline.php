<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discipline extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'discipline_type_id',
        'reporter_user_id',
        'incident_date',
        'incident_location',
        'description',
        'penalty_points',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
        'parent_notified',
        'parent_notified_at',
        'attachments'
    ];

    protected $casts = [
        'incident_date' => 'date',
        'reviewed_at' => 'datetime',
        'parent_notified_at' => 'datetime',
        'parent_notified' => 'boolean',
        'attachments' => 'array',
        'penalty_points' => 'integer'
    ];

    /** Học sinh vi phạm */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Loại vi phạm */
    public function disciplineType()
    {
        return $this->belongsTo(DisciplineType::class);
    }

    /** Người báo cáo (Giáo viên) */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** Người duyệt (Hiệu trưởng/BGH) */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** Các hành động xử lý */
    public function actions()
    {
        return $this->hasMany(DisciplineAction::class);
    }

    /** Các khiếu nại */
    public function appeals()
    {
        return $this->hasMany(DisciplineAppeal::class);
    }

    /** Scope lọc theo trạng thái */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /** Scope lọc theo học sinh */
    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /** Scope lọc theo lớp */
    public function scopeByClass($query, $classId)
    {
        return $query->whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });
    }

    /** Scope lọc theo năm học */
    public function scopeByAcademicYear($query, $yearId)
    {
        return $query->whereHas('student.schoolClass', function ($q) use ($yearId) {
            $q->where('academic_year_id', $yearId);
        });
    }

    /** Scope lọc theo khoảng thời gian */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('incident_date', [$startDate, $endDate]);
    }

    /** Scope lấy các bản ghi đã được duyệt */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /** Scope lấy các bản ghi chưa thông báo phụ huynh */
    public function scopeNotNotifiedParents($query)
    {
        return $query->where('parent_notified', false)
            ->where('status', 'confirmed');
    }

    /** Kiểm tra có phải chờ duyệt không */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Kiểm tra đã được duyệt chưa */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /** Kiểm tra có khiếu nại không */
    public function hasAppeals(): bool
    {
        return $this->appeals()->exists();
    }

    /** Tự động set penalty_points từ discipline_type nếu chưa có */
    protected static function booted()
    {
        static::creating(function ($discipline) {
            if (!$discipline->penalty_points && $discipline->disciplineType) {
                $discipline->penalty_points = $discipline->disciplineType->default_penalty_points;
            }
        });
    }
}
