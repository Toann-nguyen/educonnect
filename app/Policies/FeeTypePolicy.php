<?php

namespace App\Policies;

use App\Models\User;
use App\Models\FeeType;

class FeeTypePolicy
{
    /**
     * Determine if the user can manage fee types (create, update, delete)
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(['admin', 'principal', 'accountant']);
    }

    /**
     * Determine if the user can view fee types (everyone can view)
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific fee type
     */
    public function view(User $user, FeeType $feeType): bool
    {
        return true;
    }
}
