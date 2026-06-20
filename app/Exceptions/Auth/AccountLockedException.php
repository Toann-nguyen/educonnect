<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\Request;

class AccountLockedException extends Exception
{
    public function __construct(string $message = 'Account locked. Please try again later.')
    {
        parent::__construct($message, 423);
    }

    public function render(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'locked' => true,
            'retry_after' => 900,
        ], 423)->withHeaders([
            'Retry-After' => 900,
        ]);
    }
}
