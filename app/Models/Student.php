<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'class_id', 'student_code', 'status'];


    /** Lấy tài khoản user của học sinh */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Lấy lớp học */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Lấy danh sách người giám hộ */
    public function guardians()
    {
        return $this->hasMany(StudentGuardian::class);
    }
}
