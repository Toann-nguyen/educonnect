<?php

namespace App\Exceptions\Auth;

use Exception;

class InvalidCredentialsException extends Exception
{
    protected ?int $attemptsLeft;
    protected bool $requiresCaptcha;

    public function __construct($message = "Invalid credentials", ?int $attemptsLeft = null, bool $requiresCaptcha = false)
    {
        parent::__construct($message, 401);
        $this->attemptsLeft = $attemptsLeft;
        $this->requiresCaptcha = $requiresCaptcha;
    }

    public function getAttemptsLeft(): ?int
    {
        return $this->attemptsLeft;
    }

    public function getRequiresCaptcha(): bool
    {
        return $this->requiresCaptcha;
    }
}
