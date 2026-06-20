<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Middleware giải mã cookie refresh_token cho stateless API routes.
 *
 * Vì API group không có EncryptCookies middleware (chỉ web group mới có),
 * cookie value do frontend gửi lên là raw string — không cần decrypt.
 * Middleware này chỉ đảm bảo cookie được parse đúng từ request header.
 *
 * Lưu ý: refresh_token được list trong EncryptCookies::$except nên
 * Laravel KHÔNG mã hóa nó → $request->cookie('refresh_token') trả về raw value.
 */
class DecryptCookiesForRefresh
{
    public function handle(Request $request, Closure $next): Response
    {
        // Không cần xử lý thêm — refresh_token đã được exempt khỏi encryption.
        // Middleware này là guard để document rõ ràng flow cookie cho /refresh endpoint.
        return $next($request);
    }
}
