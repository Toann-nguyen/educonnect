<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentConductScore extends Model
{
    use HasFactory;
    protected $fillable = [
        'student_id',
        'semester',
        'academic_year_id',
        'total_penalty_points',
        'conduct_grade',
        'teacher_comment',
        'approved_by_user_id',
        'approved_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'total_penalty_points' => 'integer',
        'semester' => 'integer'
    ];

    /** Học sinh */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Năm học */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Người phê duyệt */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** Tính điểm hạnh kiểm dựa trên tổng điểm trừ */
    public function calculateConductGrade(): string
    {
        $points = $this->total_penalty_points;

        if ($points == 0) {
            return 'excellent';
        } elseif ($points <= 5) {
            return 'good';
        } elseif ($points <= 15) {
            return 'average';
        } else {
            return 'weak';
        }
    }

    /** Auto-calculate conduct grade before saving */
    protected static function booted()
    {
        static::saving(function ($conductScore) {
            if ($conductScore->isDirty('total_penalty_points') || !$conductScore->conduct_grade) {
                $conductScore->conduct_grade = $conductScore->calculateConductGrade();
            }
        });
    }

    /** Scope lọc theo học kỳ */
    public function scopeBySemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    /** Scope lọc theo năm học */
    public function scopeByAcademicYear($query, $yearId)
    {
        return $query->where('academic_year_id', $yearId);
    }

    /** Scope đã được phê duyệt */
    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_by_user_id');
    }
}
