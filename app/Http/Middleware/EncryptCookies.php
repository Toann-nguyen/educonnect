<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Refresh token cookie is set/read on the stateless API group (no cookie
        // encryption middleware). Listed here so its value stays raw if ever
        // processed by the web group too.
        'refresh_token',
    ];
}
