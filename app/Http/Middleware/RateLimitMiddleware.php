<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Configuration presets for different endpoint types.
     */
    private const CONFIGS = [
        'login'           => ['max' => 10, 'window' => 60, 'type' => 'login'],
        'register'        => ['max' => 5,  'window' => 60, 'type' => 'register'],
        'refresh'         => ['max' => 10, 'window' => 60, 'type' => 'refresh'],
        'forgot_password' => ['max' => 3,  'window' => 3600, 'type' => 'forgot_password'],
        'reset_password'  => ['max' => 3,  'window' => 3600, 'type' => 'reset_password'],
        'api'             => ['max' => 60, 'window' => 60, 'type' => 'api'],
        'sensitive'       => ['max' => 10, 'window' => 300, 'type' => 'sensitive'],
    ];

    /**
     * Default config for unrecognized types.
     */
    private const DEFAULT_CONFIG = ['max' => 100, 'window' => 60, 'type' => 'default'];

    public function __construct(
        private RateLimitService $rateLimiter
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        $config = self::CONFIGS[$type] ?? self::DEFAULT_CONFIG;

        $result = $this->rateLimiter->checkRequest(
            $request,
            $config['type'],
            $config['max'],
            $config['window']
        );

        // Add rate limit headers to every response
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', $config['max']);
        $response->headers->set('X-RateLimit-Remaining', $result['remaining']);

        if (!$result['allowed']) {
            return response()->json([
                'message'     => 'Too many requests. Please slow down.',
                'retry_after' => $result['retry_after'],
            ], 429)->withHeaders([
                'Retry-After'            => $result['retry_after'],
                'X-RateLimit-Limit'      => $config['max'],
                'X-RateLimit-Remaining'  => 0,
            ]);
        }

        return $response;
    }
}
