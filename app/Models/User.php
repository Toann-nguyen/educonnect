<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = ['email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    protected $dates = ['deleted_at'];

    /** Mối quan hệ 1-1 với Profile */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /** Mối quan hệ 1-1 với Student (nếu user này là học sinh) */
    public function student()
    {
        return $this->hasOne(Student::class);
    }
    public  function teacheringSchedules()
    {
        return $this->hasOne(Schedule::class, 'teacher_id');
    }
    public function reportedDisciplines()
    {
        return $this->hasMany(Discipline::class, 'reporter_user_id');
    }

    public function guardianStudents()
    {
        return $this->hasManyThrough(
            Student::class,
            StudentGuardian::class,
            'guardian_user_id',
            'id',
            'id',
            'student_id'
        );
    }
    /** Lấy điểm số do giáo viên này chấm */
    public function gradedScores()
    {
        return $this->hasMany(Grade::class, 'teacher_id');
    }

    /** Lấy các lớp học mà user này làm GVCN */
    public function homeroomClasses()
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }
}
