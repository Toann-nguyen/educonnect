<?php

namespace App\Models;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    // Guard name cho Spatie Permission phải khớp với config/auth.php
    protected $guard_name = 'api';

    /**
     * Các thuộc tính có thể gán hàng loạt (Mass Assignable).
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'password_hash',
        'avatar_url',
        'bio',
        'is_email_verified',
        'is_phone_verified',
        'status',
        'email_verified_at',
        'remember_token',
        'is_active',
        'is_locked',
        'locked_reason',
        'locked_at',
        'failed_login_count',
        'token_version',
        'provider',
        'provider_id',
        'totp_secret',
        'totp_secret_temp',
        'totp_enabled',
        'phone_2fa_enabled',
        'last_login_at',
    ];

    /**
     * Các thuộc tính cần ẩn khi serialize.
     */
    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
        'totp_secret',
        'totp_secret_temp',
    ];

    /**
     * Ép kiểu dữ liệu (Casts).
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_email_verified'  => 'boolean',
        'is_phone_verified'  => 'boolean',
        'is_active'          => 'boolean',
        'is_locked'          => 'boolean',
        'totp_enabled'       => 'boolean',
        'phone_2fa_enabled'  => 'boolean',
        'last_login_at'      => 'datetime',
        'locked_at'          => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    // -------------------------------------------------------
    // JWTSubject Interface Implementations
    // -------------------------------------------------------

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'type' => 'access',
            'ver'  => $this->token_version,
        ];
    }

    // -------------------------------------------------------
    // New Relationships (rediter_login.md)
    // -------------------------------------------------------

    public function emailVerification(): HasOne
    {
        return $this->hasOne(EmailVerification::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function backupCodes(): HasMany
    {
        return $this->hasMany(BackupCode::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    public function getAuthPassword(): string
    {
        // Laravel dùng method này cho Hash::check(). Ưu tiên password_hash mới, fallback về password cũ.
        return $this->password_hash ?? $this->password;
    }

    // -------------------------------------------------------
    // Existing Relationships & Scopes (EduConnect Legacy)
    // -------------------------------------------------------

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

    public function teacheringSchedules()
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
