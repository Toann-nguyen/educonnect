<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    // Guard name cho Spatie Permission phải khớp với config/auth.php
    protected $guard_name = 'api';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password_hash',
        'avatar_url',
        'bio',
        'is_email_verified',
        'is_phone_verified',
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

    protected $hidden = [
        'password_hash',
        'totp_secret',
        'totp_secret_temp',
    ];

    protected $casts = [
        'is_email_verified'  => 'boolean',
        'is_phone_verified'  => 'boolean',
        'is_active'          => 'boolean',
        'is_locked'          => 'boolean',
        'totp_enabled'       => 'boolean',
        'phone_2fa_enabled'  => 'boolean',
        'last_login_at'      => 'datetime',
        'locked_at'          => 'datetime',
    ];

    // -------------------------------------------------------
    // JWTSubject — bắt buộc implement (tymon/jwt-auth)
    // -------------------------------------------------------

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Nhúng roles/permissions vào JWT payload
     * → không cần DB lookup mỗi request
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'email'         => $this->email,
            'roles'         => $this->getRoleNames(),
            'permissions'   => $this->getAllPermissions()->pluck('name'),
            'token_version' => $this->token_version,
        ];
    }

    // -------------------------------------------------------
    // Relationships
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
        // Laravel dùng method này cho Hash::check()
        return $this->password_hash;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerification extends Model
{
    public $timestamps = false; // chỉ có created_at, tự xử lý

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'verified_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'email'    => ['required', 'string', 'email:rfc,dns', 'max:255'],
            'password' => [
                'required',
                'confirmed', // tự động check với password_confirmation field
                Password::min(8)
                    ->mixedCase()   // phải có chữ hoa + chữ thường
                    ->numbers()     // phải có ít nhất 1 số
                    ->uncompromised(), // kiểm tra trong danh sách password bị leak
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'              => 'Tên không được để trống.',
            'name.min'                   => 'Tên phải có ít nhất 2 ký tự.',
            'email.required'             => 'Email không được để trống.',
            'email.email'                => 'Email không hợp lệ.',
            'password.required'          => 'Mật khẩu không được để trống.',
            'password.confirmed'         => 'Xác nhận mật khẩu không khớp.',
            'password.min'               => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.mixed_case'        => 'Mật khẩu phải có cả chữ hoa và chữ thường.',
            'password.numbers'           => 'Mật khẩu phải có ít nhất 1 chữ số.',
            'password.uncompromised'     => 'Mật khẩu này đã bị lộ trong các vụ rò rỉ dữ liệu, vui lòng chọn mật khẩu khác.',
        ];
    }

    /**
     * Chuẩn hóa dữ liệu trước khi validate
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email ?? '')),
            'name'  => trim($this->name ?? ''),
        ]);
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface AuthRepositoryInterface
{
    /**
     * Tìm user theo email (chưa bị soft delete, không bị deactivate check ở service)
     */
    public function findByEmail(string $email): ?User;

    /**
     * Tạo user mới
     */
    public function create(array $data): User;

    /**
     * Cập nhật user theo id
     */
    public function update(int $userId, array $data): bool;
}

<?php

namespace App\Repositories\Contracts;

use App\Models\EmailVerification;

interface EmailVerificationRepositoryInterface
{
    /**
     * Tạo hoặc cập nhật token verify (upsert theo user_id)
     * Tránh tồn tại nhiều token cho cùng 1 user
     */
    public function upsert(int $userId, string $tokenHash, \Carbon\Carbon $expiresAt): EmailVerification;

    /**
     * Tìm record hợp lệ theo token_hash
     * Điều kiện: verified_at IS NULL
     */
    public function findByTokenHash(string $tokenHash): ?EmailVerification;

    /**
     * Đánh dấu đã verify
     */
    public function markAsVerified(int $id): bool;
}

<?php

namespace App\Repositories\Auth;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->model
            ->withoutTrashed()
            ->where('email', $email)
            ->first();
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(int $userId, array $data): bool
    {
        return (bool) $this->model
            ->where('id', $userId)
            ->update($data);
    }
}

<?php

namespace App\Repositories\Auth;

use App\Models\EmailVerification;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use Carbon\Carbon;

class EmailVerificationRepository implements EmailVerificationRepositoryInterface
{
    public function __construct(
        private readonly EmailVerification $model
    ) {}

    public function upsert(int $userId, string $tokenHash, Carbon $expiresAt): EmailVerification
    {
        // updateOrCreate vì user_id là unique
        // → ghi đè token cũ nếu user bấm "gửi lại"
        return $this->model->updateOrCreate(
            ['user_id' => $userId],
            [
                'token_hash'  => $tokenHash,
                'expires_at'  => $expiresAt,
                'verified_at' => null,
                'created_at'  => now(),
            ]
        );
    }

    public function findByTokenHash(string $tokenHash): ?EmailVerification
    {
        return $this->model
            ->where('token_hash', $tokenHash)
            ->whereNull('verified_at')
            ->first();
    }

    public function markAsVerified(int $id): bool
    {
        return (bool) $this->model
            ->where('id', $id)
            ->update(['verified_at' => now()]);
    }
}

<?php

namespace App\Services\Auth;

use App\Jobs\SendVerificationEmail;
use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterService
{
    public function __construct(
        private readonly AuthRepositoryInterface              $authRepository,
        private readonly EmailVerificationRepositoryInterface $emailVerificationRepository,
    ) {}

    /**
     * Đăng ký tài khoản mới
     *
     * @throws \Illuminate\Validation\ValidationException nếu email đã tồn tại
     */
    public function register(array $data): User
    {
        // 1. Kiểm tra email đã tồn tại
        if ($this->authRepository->findByEmail($data['email'])) {
            throw new \Exception('Email already registered.', 409);
        }

        // 2. Tạo raw token verify email
        $rawToken   = Str::random(64);
        $tokenHash  = hash('sha256', $rawToken);
        $expiresAt  = now()->addHours(24);

        // 3. Wrap trong transaction để đảm bảo tính toàn vẹn
        $user = DB::transaction(function () use ($data, $tokenHash, $expiresAt) {
            // Tạo user
            $user = $this->authRepository->create([
                'name'               => $data['name'],
                'email'              => $data['email'],
                'password_hash'      => Hash::make($data['password'], ['rounds' => 12]),
                'is_email_verified'  => false,
                'is_active'          => true,
                'token_version'      => 1,
            ]);

            // Gán role mặc định
            $user->assignRole('user');

            // Tạo / cập nhật token verify email
            $this->emailVerificationRepository->upsert(
                $user->id,
                $tokenHash,
                $expiresAt
            );

            return $user;
        });

        // 4. Dispatch job gửi email (ngoài transaction, không block response)
        SendVerificationEmail::dispatch($user, $rawToken)
            ->onQueue('emails');

        return $user;
    }
}