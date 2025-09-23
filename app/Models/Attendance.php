<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'schedule_id', 'date', 'status', 'note'];

    /** Lấy thông tin học sinh */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Lấy thông tin buổi học */
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
