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

use App\Services\Auth\RegisterService;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }
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
