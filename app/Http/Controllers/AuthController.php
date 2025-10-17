<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Interface\AuthServiceInterface;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }
    public function register(RegisterRequest $request)
    {
        try {
            $user = $this->authService->register($request->validated());

            return response()->json([
                'message' => 'User registered successfully!',
                'data' => new UserResource($user)
            ], 201);
        } catch (Exception $e) {
            // Ghi log lỗi không mong muốn
            Log::error('Registration failed: ' . $e->getMessage(), [
                'email' => $request->user()->email,

                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'An unexpected error occurred during registration.'], 500);
        }
    }
    public function login(LoginRequest $request)
    {

        try {
            $result = $this->authService->login($request->validated());
            return response()->json([
                'message' => 'Login successful!',
                'access_token' => $result['token'],
                'token_type' => 'Bearer',
                'data' => new UserResource($result['user'])
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status ?? 422);
        } catch (AuthorizationException $e) {
            Log::warning('Authorization failed during login: ' . $e->getMessage(), [
                'email' => $request->user()->email,

                'ip' => $request->ip()
            ]);
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Exception $e) {
            Log::error('Login failed with unexpected error: ' . $e->getMessage(), [
                'email' => $request->user()->email,

                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An unexpected error occurred during login.'], 500);
        }
    }
    public function logout(Request $request)
    {
        try {
            $this->authService->logout($request->user());

            return response()->json([
                'message' => 'Successfully logged out'
            ]);
        } catch (Exception $e) {
            Log::error('Logout failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An error occurred during logout.'], 500);
        }
    }
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $status = $this->authService->forgotPassword($request->validated());
            dd($status);
            // Ghi log trường hợp không gửi được link mà không rõ lý do
            Log::warning('Failed to send password reset link.', ['email' => $request->email, 'status' => $status]);
            return response()->json(['message' => 'Failed to send password reset link.', 'status' => $status], 400);
        } catch (Exception $e) {
            Log::error('Forgot password failed: ' . $e->getMessage(), [
                'email' => $request->user()->email,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $status = $this->authService->resetPassword($request->validated());
            if ($status) {
                return response()->json([
                    'message' => 'Password has been reset.'
                ], 200);
            }
            // token không hợp lệ
            return response()->json(['message' => 'Failed to reset password. The token may be invalid or expired.', 'status' => $status], 400);
        } catch (Exception $e) {
            Log::error('Reset password failed: ' . $e->getMessage(), [
                'email' => $request->user()->email,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
    public function user(Request $request)
    {

        try {
            return response()->json([
                'data' => $request->user()->load('profile', 'roles')
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
