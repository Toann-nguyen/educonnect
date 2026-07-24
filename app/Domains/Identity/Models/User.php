<?php

namespace App\Domains\Identity\Models;

// Cross-domain relationships trỏ qua user_id, không import model của domain khác
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
    protected $connection = 'identity';
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
    // Identity Domain Relationships (chỉ quan hệ trong Identity)
    // -------------------------------------------------------

    /** Mối quan hệ 1-1 với Profile */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    // -------------------------------------------------------
    // Cross-domain: chỉ expose user_id, không import Model ngoài domain
    // School/Finance truy vấn bằng user_id qua service của chính chúng
    // -------------------------------------------------------

    /** Lấy user_id để School domain tự truy vấn student tương ứng */
    public function getUserIdAttribute(): int
    {
        return $this->id;
    }
}
