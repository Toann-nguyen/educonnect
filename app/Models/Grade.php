<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'subject_id', 'teacher_id', 'score', 'type', 'semester'];


    /** Lấy thông tin học sinh */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Lấy thông tin môn học */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /** Lấy thông tin giáo viên đã cho điểm */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
