<?php

namespace App\Policies;

use App\Models\Discipline;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DisciplinePolicy
{
    // ...

    /**
     * Ai có quyền duyệt một bản ghi kỷ luật.
     */
    public function approve(User $user, Discipline $discipline): bool
    {
        return $user->hasRole(['admin', 'principal']);
    }

    /**
     * Ai có quyền từ chối một bản ghi kỷ luật.
     */
    public function reject(User $user, Discipline $discipline): bool
    {
        return $user->hasRole(['admin', 'principal']);
    }

    // ====================================================================
    // == CÁC QUYỀN XỬ LÝ KHIẾU NẠI LIÊN QUAN ĐẾN KỶ LUẬT
    // == Thêm các hàm này vào
    // ====================================================================

    /**
     * Ai có quyền duyệt một KHIẾU NẠI (dẫn đến hủy kỷ luật).
     */
    public function approveAppeal(User $user, Discipline $discipline): bool
    {
        // Chỉ Admin/Principal mới có quyền này
        return $user->hasRole(['admin', 'principal']);
    }

    /**
     * Ai có quyền từ chối một KHIẾU NẠI (giữ nguyên kỷ luật).
     */
    public function rejectAppeal(User $user, Discipline $discipline): bool
    {
        // Chỉ Admin/Principal mới có quyền này
        return $user->hasRole(['admin', 'principal']);
    }
}
// class DisciplinePolicy
// {
//     /**
//      * Determine whether the user can view any models.
//      */
//     public function viewAny(User $user): bool
//     {
//         //
//     }

//     /**
//      * Determine whether the user can view the model.
//      */
//     public function view(User $user, Discipline $discipline): bool
//     {
//         //
//     }

//     /**
//      * Determine whether the user can create models.
//      */
//     public function create(User $user): bool
//     {
//         //
//     }

//     /**
//      * Determine whether the user can update the model.
//      */
//     public function update(User $user, Discipline $discipline): bool
//     {
//         //
//     }

//     /**
//      * Determine whether the user can delete the model.
//      */
//     public function delete(User $user, Discipline $discipline): bool
//     {
//         //
//     }

//     /**
//      * Determine whether the user can restore the model.
//      */
//     public function restore(User $user, Discipline $discipline): bool
//     {
//         //
//     }

//     /**
//      * Determine whether the user can permanently delete the model.
//      */
//     public function forceDelete(User $user, Discipline $discipline): bool
//     {
//         //
//     }
// }