<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Interface\AuthServiceInterface;
use App\Services\PermissionCacheService;
use Exception;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\LoginResource;
use App\Http\Resources\ProfileResource;
use Illuminate\Support\Facades\Password;

use App\Services\Auth\RegisterService;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Đăng ký tài khoản mới",
     *     description="Đăng ký tài khoản người dùng mới. Sau khi đăng ký thành công, email xác thực sẽ được gửi đến địa chỉ email đã cung cấp.",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="Nguyễn Văn A", description="Tên người dùng (2-100 ký tự)"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com", description="Địa chỉ email hợp lệ"),
     *             @OA\Property(property="password", type="string", format="password", example="Password@123", description="Mật khẩu (tối thiểu 8 ký tự, bao gồm chữ hoa, chữ thường và số)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="Password@123", description="Xác nhận mật khẩu (phải khớp với password)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Đăng ký thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Registration successful. Please check your email for verification."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="status", type="string", example="UNVERIFIED")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Lỗi xác thực dữ liệu",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="Email đã tồn tại.")
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="array",
     *                     @OA\Items(type="string", example="Mật khẩu phải có ít nhất 8 ký tự.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Quá nhiều yêu cầu (rate limit)"
     *     )
     * )
     */
    public function register(RegisterRequest $request, RegisterService $registerService)
    {
        try {
            $user = $registerService->register($request->validated());

            return response()->json([
                'message' => 'Registration successful. Please check your email for verification.',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'status' => 'UNVERIFIED'
                ]
            ], 201);
        } catch (Exception $e) {
            // Ghi log lỗi không mong muốn (không ghi log lỗi validation 422)
            if ($e->getCode() !== 422) {
                Log::error('Registration failed: ' . $e->getMessage(), [
                    'email' => $request->input('email') ?? null,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'An unexpected error occurred during registration.'
            ], $e->getCode() ?: 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        try {
            $this->authService->verifyEmail($request->token);
            
            return response()->json([
                'message' => 'Email verified successfully.'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status ?? 422);
        } catch (Exception $e) {
            Log::error('Email verification failed: ' . $e->getMessage());
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
    public function login(LoginRequest $request)
    {
        try {
            $result = $this->authService->login($request->validated());

            if (isset($result['requires_2fa']) && $result['requires_2fa'] === true) {
                return response()->json([
                    'requires_2fa' => true,
                    'pre_auth_token' => $result['pre_auth_token']
                ]);
            }

            // Refresh token được trả qua HttpOnly cookie (chống XSS), KHÔNG nằm trong body.
            // Access token (15 phút) frontend lưu in-memory và gửi qua Authorization: Bearer.
            return response()->json([
                'message' => 'Login successful!',
                'access_token' => $result['access_token'],
                'expires_in' => config('jwt.ttl') * 60,
                'token_type' => 'Bearer',
                'data' => new LoginResource($result['user'])
            ])->withCookie($this->makeRefreshCookie($result['refresh_token']));
        } catch (\App\Exceptions\Auth\IPSpamException $e) {
            return $e->render($request);
        } catch (\App\Exceptions\Auth\AccountLockedException $e) {
            return $e->render($request);
        } catch (\App\Exceptions\Auth\CaptchaRequiredException $e) {
            return $e->render($request);
        } catch (\App\Exceptions\Auth\InvalidCredentialsException $e) {
            $response = [
                'message' => $e->getMessage()
            ];
            if ($e->getAttemptsLeft() !== null) {
                $response['attempts_left'] = $e->getAttemptsLeft();
                $response['requires_captcha'] = $e->getRequiresCaptcha();
            }
            return response()->json($response, 401);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status ?? 422);
        } catch (AuthorizationException $e) {
            Log::warning('Authorization failed during login: ' . $e->getMessage(), [
                'email' => $request->input('email'),
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Exception $e) {
            Log::error('Login failed with unexpected error: ' . $e->getMessage(), [
                'email' => $request->input('email'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An unexpected error occurred during login.'], 500);
        }
    }
    public function logout(Request $request)
    {
        try {
            $this->authService->logout($request->user(), $request->cookie('refresh_token'));

            return response()->json([
                'message' => 'Successfully logged out'
            ])->withCookie($this->forgetRefreshCookie());
        } catch (Exception $e) {
            Log::error('Logout failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An error occurred during logout.'], 500);
        }
    }

    public function logoutAll(Request $request)
    {
        try {
            $this->authService->logoutAll($request->user());

            return response()->json([
                'message' => 'Logged out from all devices'
            ])->withCookie($this->forgetRefreshCookie());
        } catch (Exception $e) {
            Log::error('Logout-all failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An error occurred during logout.'], 500);
        }
    }

    /**
     * Cấp lại access token bằng refresh token (đọc từ HttpOnly cookie).
     * Endpoint này KHÔNG yêu cầu Bearer token vì access token có thể đã hết hạn.
     */
    public function refresh(Request $request)
    {
        $rawRefreshToken = $request->cookie('refresh_token');

        if (!$rawRefreshToken) {
            return response()->json(['message' => 'Refresh token not provided'], 401);
        }

        try {
            $result = $this->authService->refresh($rawRefreshToken);

            return response()->json([
                'message' => 'Token refreshed',
                'access_token' => $result['access_token'],
                'expires_in' => config('jwt.ttl') * 60,
                'token_type' => 'Bearer',
                'data' => new LoginResource($result['user'])
            ])->withCookie($this->makeRefreshCookie($result['refresh_token']));
        } catch (\App\Exceptions\Auth\InvalidCredentialsException $e) {
            return response()->json(['message' => $e->getMessage()], 401)
                ->withCookie($this->forgetRefreshCookie());
        } catch (Exception $e) {
            Log::error('Token refresh failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Tạo HttpOnly cookie chứa refresh token.
     *
     * Path = '/api/auth/' (có trailing slash) → browser tự động gửi cookie
     * cho tất cả sub-path: /api/auth/refresh, /api/auth/logout, /api/auth/logout/all.
     * Không dùng '/' (quá rộng) — tối thiểu scope cần thiết (Principle of Least Privilege).
     *
     * Secure=true  → chỉ gửi qua HTTPS (production). Dev local dùng HTTP cần tắt.
     * HttpOnly=true → JS không đọc được → chống XSS tuyệt đối.
     * SameSite=Strict → không gửi trong cross-site request → chống CSRF.
     */
    private function makeRefreshCookie(string $value)
    {
        return cookie(
            name: 'refresh_token',
            value: $value,
            minutes: 60 * 24 * 7,                          // 7 ngày
            path: '/api/auth/',                             // trailing slash bắt buộc
            domain: null,
            secure: app()->isProduction(),                  // HTTPS-only trên production
            httpOnly: true,
            raw: false,
            sameSite: 'Strict'
        );
    }

    private function forgetRefreshCookie()
    {
        return cookie(
            name: 'refresh_token',
            value: '',
            minutes: -1,
            path: '/api/auth/',
            domain: null,
            secure: app()->isProduction(),
            httpOnly: true,
            raw: false,
            sameSite: 'Strict'
        );
    }
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $status = $this->authService->forgotPassword($request->validated());
            
            if ($status === Password::RESET_LINK_SENT) {
                return response()->json(['message' => __($status)]);
            }

            return response()->json(['message' => __($status)], 400);
        } catch (Exception $e) {
            Log::error('Forgot password failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $status = $this->authService->resetPassword($request->validated());
            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => __($status)
                ], 200);
            }
            // token không hợp lệ
            return response()->json(['message' => __($status)], 400);
        } catch (Exception $e) {
            Log::error('Reset password failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $cacheService = app(PermissionCacheService::class);
            $cached = $cacheService->get($user->id);

            return response()->json([
                'data' => [
                    'id'    => $user->id,
                    'email' => $user->email,
                    'profile' => new ProfileResource($user->profile),
                    'roles' => $cached['roles'],
                    'permissions' => $cached['permissions'],
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch authenticated user: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Could not retrieve user information.'], 500);
        }
    }
}
