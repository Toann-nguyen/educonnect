<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\Request;

class CaptchaRequiredException extends Exception
{
    public function __construct(string $message = 'Captcha required')
    {
        parent::__construct($message, 403);
    }

    public function render(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'requires_captcha' => true,
        ], 403);
    }
}
