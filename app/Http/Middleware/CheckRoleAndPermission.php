<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleAndPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$rolesOrPermissions): Response
    {
        // Bước 1: Kiểm tra xem người dùng đã được xác thực chưa.
        // Nếu chưa, middleware 'auth:sanctum' đã xử lý và ném ra lỗi rồi,
        // nhưng chúng ta vẫn nên kiểm tra để đảm bảo.
        if (!$request->user()) {
            throw new AuthenticationException();
        }

        // Bước 2: Lấy đối tượng người dùng đã được xác thực.
        $user = $request->user();

        // Bước 3: Nếu không có yêu cầu role/permission cụ thể nào, cho phép đi tiếp.
        if (empty($rolesOrPermissions)) {
            return $next($request);
        }

        // Bước 4: Sử dụng hàm có sẵn của Spatie để kiểm tra.
        // hasAnyRole(): Kiểm tra xem user có BẤT KỲ vai trò nào trong danh sách không (OR logic).
        // hasAnyPermission(): Kiểm tra xem user có BẤT KỲ quyền nào trong danh sách không (OR logic).
        // hasAllRoles(): Kiểm tra xem user có TẤT CẢ các vai trò trong danh sách không (AND logic).
        // hasAllPermissions(): Kiểm tra xem user có TẤT CẢ các quyền trong danh sách không (AND logic).

        // Chúng ta sẽ dùng hasAnyRole và hasAnyPermission vì nó phù hợp với cách truyền tham số qua |
        // Duyệt qua từng chuỗi yêu cầu được truyền vào.
        // Ví dụ: middleware('access:admin', 'access:manage finances')
        // Mỗi chuỗi này được coi là một nhóm điều kiện OR.
        // Người dùng chỉ cần thỏa mãn MỘT trong các chuỗi này là được.
        foreach ($rolesOrPermissions as $requirementString) {

            // Kiểm tra xem chuỗi yêu cầu có chứa logic AND (&) hay không
            if (str_contains($requirementString, '&')) {
                // LOGIC AND: Người dùng phải có TẤT CẢ các vai trò/quyền trong chuỗi
                $andRequirements = explode('&', $requirementString);

                // Sử dụng hàm hasAllRoles() hoặc hasAllPermissions() của Spatie
                if ($user->hasAllRoles($andRequirements) || $user->hasAllPermissions($andRequirements)) {
                    return $next($request);
                }
            } else {
                // LOGIC OR: Người dùng chỉ cần có MỘT trong các vai trò/quyền trong chuỗi
                // Hàm hasAnyRole() và hasAnyPermission() của Spatie đã tự động xử lý dấu '|'
                if ($user->hasAnyRole($requirementString) || $user->hasAnyPermission($requirementString)) {
                    return $next($request);
                }
            }
        }
        // Nếu không thỏa mãn bất kỳ yêu cầu nào, ném ra lỗi AuthorizationException.
        // Laravel sẽ tự động bắt lỗi này và trả về response 403 Forbidden.
        throw new AuthorizationException('This action is unauthorized.');
    }
}
