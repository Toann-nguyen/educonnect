<?php

namespace App\Domains\School\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGuardian extends Model
{
    protected $connection = 'school';
    use HasFactory;

    protected $fillable = ['student_id', 'guardian_user_id', 'relationship'];

    /** Lấy thông tin học sinh */
    public function student()
    {
        return $this->belongsTo(Student::class,  'student_id');
    }

}
