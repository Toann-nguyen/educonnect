<?php

namespace App\Domains\School\Services\Interface;

use App\Domains\Identity\Models\User;

interface DashBoardServiceInterface
{
    /**
     * Lấy dữ liệu Dashboard dựa trên vai trò của người dùng.
     *
     * @param User $user
     * @return array
     */
    public function getDataForUser(User $user);
}
