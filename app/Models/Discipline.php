<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discipline extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['student_id', 'reporter_user_id', 'description', 'date'];

    /** Lấy thông tin học sinh vi phạm */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Lấy thông tin người báo cáo */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }
}
