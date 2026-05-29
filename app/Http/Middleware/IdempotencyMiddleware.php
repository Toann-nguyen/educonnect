<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Xử lý request đầu vào.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Chỉ áp dụng cho các request ghi dữ liệu (POST, PUT, PATCH)
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // 2. Lấy Idempotency-Key từ Header
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $lockKey = "idempotency_lock:{$idempotencyKey}";
        $responseKey = "idempotency_response:{$idempotencyKey}";

        // 3. Nếu response cũ đã được lưu trong Redis, trả về ngay lập tức
        if (Redis::exists($responseKey)) {
            $cached = json_decode(Redis::get($responseKey), true);
            return response()->json(
                $cached['content'] ?? [],
                $cached['status'] ?? 200,
                $cached['headers'] ?? []
            );
        }

        // 4. Sử dụng Redis Lock để tránh Race Condition (Concurrent Request)
        // Set key với NX (chỉ set nếu chưa có) và EX (hết hạn sau 60 giây)
        $acquired = Redis::set($lockKey, 'processing', 'EX', 60, 'NX');

        if (!$acquired) {
            return response()->json([
                'message' => 'Request đang được xử lý, vui lòng không gửi lại liên tục.'
            ], 409);
        }

        try {
            $response = $next($request);

            // 5. Chỉ lưu cache response đối với các request thành công (200, 201)
            if ($response->isSuccessful()) {
                $cachedData = [
                    'status' => $response->getStatusCode(),
                    'headers' => collect($response->headers->all())->map(fn($v) => $v[0] ?? '')->toArray(),
                    'content' => json_decode($response->getContent(), true) ?: $response->getContent(),
                ];
                // Lưu vào Redis với TTL 24 giờ (86400 giây)
                Redis::set($responseKey, json_encode($cachedData), 'EX', 86400);
            }

            return $response;
        } finally {
            // Giải phóng lock sau khi xử lý xong
            Redis::del($lockKey);
        }
    }
}
