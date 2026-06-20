<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\Request;

class IPSpamException extends Exception
{
    public int $retryAfter;
    public string $blockedBy; // 'ip' or 'pair'

    public function __construct(
        string $message = 'Too many requests. Please slow down.',
        int $retryAfter = 60,
        string $blockedBy = 'ip'
    ) {
        parent::__construct($message, 429);
        $this->retryAfter = $retryAfter;
        $this->blockedBy = $blockedBy;
    }

    public function render(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'retry_after' => $this->retryAfter,
            'blocked_by' => $this->blockedBy,
        ], 429)->withHeaders([
            'Retry-After' => $this->retryAfter,
            'X-RateLimit-Limit' => $this->blockedBy === 'ip' ? 30 : 10,
            'X-RateLimit-Remaining' => 0,
        ]);
    }
}
