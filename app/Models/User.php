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


    protected $fillable = ['email', 'password', 'status', 'email_verified_at', 'remember_token'];
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
    /** Các thanh toán cho hóa đơn này */
    public function payments()
    {
        return $this->hasMany(Payment::class);
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
        return $this->belongsToMany(
            Student::class,
            'student_guardians',    // Tên bảng trung gian
            'guardian_user_id',     // Khóa ngoại trên bảng trung gian trỏ về User (phụ huynh)
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

    /** Scope lọc theo lớp học */
    public function scopeByClass($query, $classId)
    {
        return $query->whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });
    }

    /** THÊM: Các khiếu nại do user này tạo */
    public function disciplineAppeals()
    {
        return $this->hasMany(DisciplineAppeal::class, 'appellant_user_id');
    }

    /** THÊM: Các khiếu nại mà user này xem xét */
    public function reviewedAppeals()
    {
        return $this->hasMany(DisciplineAppeal::class, 'reviewed_by_user_id');
    }

    /** THÊM: Các bản ghi kỷ luật mà user này duyệt */
    public function reviewedDisciplines()
    {
        return $this->hasMany(Discipline::class, 'reviewed_by_user_id');
    }

    /** THÊM: Các hành động xử lý do user này thực hiện */
    public function executedDisciplineActions()
    {
        return $this->hasMany(DisciplineAction::class, 'executed_by_user_id');
    }

    /** THÊM: Các điểm hạnh kiểm do user này phê duyệt */
    public function approvedConductScores()
    {
        return $this->hasMany(StudentConductScore::class, 'approved_by_user_id');
    }
}
