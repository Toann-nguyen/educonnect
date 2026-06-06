<?php

namespace App\Services\Interface;

interface AuthServiceInterface
{
    public function register(array $data);
    public function login(array $credentials);
    public function logout($user);
    public function forgotPassword(array $data);
    public function verifyEmail(string $token);
    public function resetPassword(array $data);
}
