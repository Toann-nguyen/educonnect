<?php

namespace App\Observers;

use App\Models\User;
use App\Services\PermissionCacheService;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    public function updated(User $user): void
    {
        // Chỉ xử lý khi các trường ảnh hưởng quyền truy cập thay đổi.
        if (!$user->wasChanged(['is_active', 'is_locked', 'status'])) {
            return;
        }

        // Xóa cache roles/permissions.
        app(PermissionCacheService::class)->clearUser($user->id);

        // Tăng token_version bằng raw query để KHÔNG kích hoạt lại observer 'updated'
        // (tránh đệ quy / vòng lặp với increment()->save()).
        DB::table('users')
            ->where('id', $user->id)
            ->update(['token_version' => DB::raw('token_version + 1')]);
    }
}
