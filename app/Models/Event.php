<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'description', 'date'];

    protected $casts = [
        'date' => 'datetime'
    ];

    /** Lấy danh sách đăng ký tham gia sự kiện */
    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    /** Lấy danh sách học sinh đã đăng ký */
    public function registeredStudents()
    {
        return $this->hasManyThrough(Student::class, EventRegistration::class, 'event_id', 'id', 'id', 'student_id');
    }
}
