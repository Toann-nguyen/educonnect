<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'classes';
    protected $fillable = ['name', 'academic_year_id', 'homeroom_teacher_id'];
    /** Lấy danh sách học sinh trong lớp */
    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /** Lấy thông tin giáo viên chủ nhiệm */
    public function homeroomTeacher()
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    /** Lấy năm học */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Lấy thời khóa biểu (schedules) của lớp */
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }
}
