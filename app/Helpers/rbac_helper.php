<?php

namespace App\Helpers;

use App\Services\RolePermissionService;

if (!function_exists('rbac')) {
    function rbac(): RolePermissionService
    {
        return app(RolePermissionService::class);
    }
}

if (!function_exists('userCan')) {
    function userCan($user, $permissions): bool
    {
        return rbac()->can($user, $permissions);
    }
}

if (!function_exists('userHasRole')) {
    function userHasRole($user, $roles): bool
    {
        return rbac()->hasRole($user, $roles);
    }
}


