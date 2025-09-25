<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    use HasFactory;
    protected $fillable = ['event_id', 'student_id', 'status'];

    /** Lấy sự kiện */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /** Lấy học sinh */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
