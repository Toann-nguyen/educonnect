<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGuardian extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'guardian_user_id', 'relationship'];

    /** Lấy thông tin học sinh */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Lấy tài khoản user của người giám hộ */
    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }
}
