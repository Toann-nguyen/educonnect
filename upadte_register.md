class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'avatar_url'         => $this->avatar_url,
            'is_email_verified'  => $this->is_email_verified,
            'is_phone_verified'  => $this->is_phone_verified,
            'totp_enabled'       => $this->totp_enabled,
            'roles'              => $this->getRoleNames(),
            'created_at'         => $this->created_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Jobs;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retry tối đa 3 lần nếu SMTP fail
    public int $tries = 3;

    // Delay giữa các lần retry: 30s, 60s, 120s
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(
        private readonly User   $user,
        private readonly string $rawToken, // gửi raw token đến email
    ) {}

    public function handle(): void
    {
        Mail::to($this->user->email)
            ->send(new VerifyEmailMail($this->user, $this->rawToken));
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verifyUrl;

    public function __construct(
        public readonly User   $user,
        private readonly string $rawToken,
    ) {
        // URL chứa raw token — gửi đến email user
        $this->verifyUrl = config('app.frontend_url')
            . '/auth/email/verify?token='
            . $this->rawToken;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác minh địa chỉ email của bạn',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.verify-email',
            with: [
                'user'      => $this->user,
                'verifyUrl' => $this->verifyUrl,
                'expiresIn' => '24 giờ',
            ],
        );
    }
}

@component('mail::message')
# Xác minh địa chỉ email

Xin chào **{{ $user->name }}**,

Cảm ơn bạn đã đăng ký tài khoản. Vui lòng nhấn nút bên dưới để xác minh địa chỉ email của bạn.

@component('mail::button', ['url' => $verifyUrl, 'color' => 'primary'])
Xác minh Email
@endcomponent

Link này sẽ hết hạn sau **{{ $expiresIn }}**.

Nếu bạn không tạo tài khoản, hãy bỏ qua email này.

Trân trọng,<br>
{{ config('app.name') }}

---
<small>Nếu nút không hoạt động, copy link sau vào trình duyệt:<br>{{ $verifyUrl }}</small>
@endcomponent

<?php

namespace App\Providers;

use App\Repositories\Auth\AuthRepository;
use App\Repositories\Auth\EmailVerificationRepository;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interfaces → implementations
        // Thêm vào đây khi code thêm các Repository khác
        $this->app->bind(
            AuthRepositoryInterface::class,
            AuthRepository::class,
        );

        $this->app->bind(
            EmailVerificationRepositoryInterface::class,
            EmailVerificationRepository::class,
        );
    }

    public function boot(): void
    {
        //
    }
}

<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes — Public (không cần JWT)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {

    // POST /api/auth/register
    Route::post('register', [AuthController::class, 'register']);

    // ... các route khác sẽ thêm vào đây
    // Route::post('login', [AuthController::class, 'login']);
    // Route::post('login/phone', [AuthController::class, 'sendPhoneOtp']);
    // Route::post('token/refresh', [TokenController::class, 'refresh']);
    // Route::get('oauth/{provider}', [OAuthController::class, 'redirect']);
});

/*
|--------------------------------------------------------------------------
| Auth Routes — Protected (cần JWT)
|--------------------------------------------------------------------------
*/
Route::middleware('auth.jwt')->prefix('auth')->group(function () {

    // GET /api/auth/me
    // Route::get('me', [AuthController::class, 'me']);

    // POST /api/auth/logout
    // Route::post('logout', [AuthController::class, 'logout']);
});