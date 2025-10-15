<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\RoleMiddleware;

class JsonRoleMiddleware extends RoleMiddleware
{
    /**
     * Ghi đè (override) phương thức handle gốc.
     */
    public function handle($request, Closure $next, $role, $guard = null): Response
    {
        try {
            // Gọi đến phương thức handle() của class cha (RoleMiddleware gốc)
            // để nó thực hiện tất cả logic kiểm tra phức tạp.
            return parent::handle($request, $next, $role, $guard);
        } catch (UnauthorizedException $e) {

            // BẮT LẠI EXCEPTION VÀ CHUYỂN THÀNH RESPONSE JSON
            // Thay vì để nó ném ra lỗi, chúng ta sẽ bắt lại và trả về JSON.
            return response()->json([
                'message' => 'User khong co role de thuc thi.',

                'required_roles' => explode('|', $role) // Hiển thị các vai trò yêu cầu
            ], 403);
        }
    }
}
