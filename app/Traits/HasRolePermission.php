<?php

namespace App\Traits;

trait HasRolePermission
{
    public function assignRole($roles)
    {
        \App\Helpers\rbac()->assignRoleToUser($this, $roles);
        return $this;
    }

    public function hasRole($roles): bool
    {
        return \App\Helpers\rbac()->hasRole($this, $roles);
    }

    public function can($permissions): bool
    {
        return \App\Helpers\rbac()->can($this, $permissions);
    }

    public function getRoles()
    {
        return \App\Helpers\rbac()->getUserRoles($this);
    }

    public function getPermissions()
    {
        return \App\Helpers\rbac()->getUserPermissions($this);
    }
}


