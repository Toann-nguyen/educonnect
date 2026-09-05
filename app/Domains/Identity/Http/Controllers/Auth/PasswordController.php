<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * API: đổi mật khẩu khi đã đăng nhập — T5.1 bump tv + revoke all.
     */
    public function change(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        // T5.1: dùng AuthService::changePassword để bump tv + revoke
        app(\App\Domains\Identity\Services\Interface\AuthServiceInterface::class)
            ->changePassword($user, Hash::make($validated['password']));

        return response()->json(['message' => 'Password changed successfully. Please login again.']);
    }

    /**
     * Legacy web redirect (giữ lại để không vỡ Inertia/Breeze nếu còn dùng).
     */
    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back();
    }
}
