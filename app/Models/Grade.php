<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'subject_id',
        'teacher_id',
        'score',
        'type',
        'semester'
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'semester' => 'integer'
    ];
    /** Học sinh nhận điểm */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Môn học */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /** Giáo viên chấm điểm */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Scope để lọc theo học kỳ */
    public function scopeBySemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    /** Scope để lọc theo loại điểm */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /** Scope để lọc theo lớp học thông qua student */
    public function scopeByClass($query, $classId)
    {
        return $query->whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });
    }
}
