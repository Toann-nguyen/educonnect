<?php

namespace App\Domains\Identity\Services\Interface;

use App\Domains\Identity\Models\User;

interface AuthServiceInterface
{
    public function register(array $data);
    public function login(array $credentials);
    public function refresh(string $refreshToken);
    public function logout($user, ?string $refreshToken = null);
    public function logoutAll($user);
    public function forgotPassword(array $data);
    public function verifyEmail(string $token);
    public function resetPassword(array $data);
    public function changePassword(User $user, string $hashedPassword): void;
}
