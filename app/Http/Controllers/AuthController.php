<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'status' => 'active'
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * validate request
     * gioi han so lan login IP
     * check mail
     * check account co dang bi lock khong (status)
     * check password dung hay sai
     * check trang thai account (active)
     *reset login attempts + cap nhat lai login
     * tao token -> ghi log thanh cong (log activity)
     * clear rate limiter (xoa key rate limiter)
     */
    public function login(Request $request)
    {
        try {
            // dinh dang request dau vao dung voi email , password
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
            $key = 'login' . $request->ip();
            // chinh lai rate limiting
            // tooManyAttempts() kiem tra 5 lan thu
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => 'To many login attempts',
                ], 429);
            }

            // lay email
            $user = User::where('email', $credentials['email'])->first();

            // kiem tra email
            // hit de kiem tra voi key email nay neu ma sau  khi dang nhap loi ma khong thuc hien them login nao thanh cong
            // thi se chan luon IP luon cua nguoi dung
            if ($user) {
                RateLimiter::hit($key, 300);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }
            // if ($user->status === 0) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Account is temporaily locked',
            //     ], 432);
            // }

            // check password trong DB co trung voi $user->password khong
            if (!Hash::check($credentials['password'], $user->password)) {
                // increment() dung de tang cot 'login_attempts' len 1 .
                // de kiem tra xem la user->login_attempts co qua so lan cho phep khong. neu qua thi locked
                $user->increment('login_attempts');

                if ($user->login_attempts >= 5) {
                    $user->update(['locked_until'  => now()->addMinutes(30)]);
                    $this->activityLogService->Log(
                        $user->id,
                        'account_locked',
                        'account locked due to multiple failed login attempts'
                    );
                }
                RateLimiter::hit($key, 300);
                return response()->json([
                    'success' => true,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'success' => true,
                    'message' => 'Account is not active',
                ], 403);
            }
            // reset login lai tu dau
            $user->update([
                'login_attempts' => 0,
                'locked_at' => null,
                'last_login_at' => now(),
            ]);
            // tao token cho user
            $token = $user->createToken('api-token')->plainTextToken();

            // thong bao Log successful login
            // thong bao tra ve he thong user login thanh cong
            $this->activityLogService->Log(
                $user->id,
                'login',
                'User logged in successful ',
            );

            RateLimiter::clear($key);
            //thong bao cho nguooi dung login thanh cong
            return response()->json([
                'success' => true,
                'message' => 'login is successful',
                'data' => [
                    'token' => $token,
                    'user' => $user->load('perrmission'),
                ],
            ]);
        } catch (Exception $e) {
            dd($e);
            return response()->json([
                'success' => false,
                'message' => 'login failed',
                'errore' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * lay thong tin user sau dos xoa token
     * Thong bao Log o he thong
     * Thong bao phia user
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $this->activityLogService->Log(
            $user->id,
            'logout',
            'User logged out',
        );

        return response()->json([
            'success' => true,
            'message' => 'User logged out sucessfully',

        ]);
    }
}
