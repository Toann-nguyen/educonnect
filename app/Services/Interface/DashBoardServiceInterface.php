<?php

namespace App\Services\Interface;

use App\Models\User;

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
