<?php

namespace App\Domains\Identity\Middleware;

use App\Http\Middleware\CheckRoleAndPermission as BaseCheck;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleAndPermission extends BaseCheck
{
    public function handle(Request $request, Closure $next, ...$rolesOrPermissions): Response
    {
        return parent::handle($request, $next, ...$rolesOrPermissions);
    }
}
