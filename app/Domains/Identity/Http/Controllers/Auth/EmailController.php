<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Jobs\SendVerificationEmail;
use App\Domains\Identity\Repositories\Contracts\EmailVerificationRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class EmailController extends Controller
{
    public function __construct(
        private readonly EmailVerificationRepositoryInterface $emailVerificationRepository,
    ) {}

    /**
     * Gửi lại email xác thực cho user đã đăng nhập.
     */
    public function send(Request $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_email_verified) {
            return response()->json(['message' => 'Email already verified'], 200);
        }

        // Tạo token verify mới (raw token cho email, hash lưu DB)
        $rawToken  = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = now()->addHours(24);

        $this->emailVerificationRepository->upsert($user->id, $tokenHash, $expiresAt);

        SendVerificationEmail::dispatch($user, $rawToken)
            ->onQueue('emails')
            ->afterCommit();

        return response()->json(['message' => 'Verification email sent.'], 200);
    }
}
