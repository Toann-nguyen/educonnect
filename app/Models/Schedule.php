<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = ['class_id', 'subject_id', 'teacher_id', 'day_of_week', 'period', 'room'];

    /** Lấy thông tin lớp học */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Lấy thông tin môn học */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /** Lấy thông tin giáo viên giảng dạy */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
