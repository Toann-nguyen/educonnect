<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // When `supports_credentials` is true, `allowed_origins` MUST NOT contain
    // the wildcard `*`. The browser will reject the response. Use the explicit
    // frontend origins (dev + production) instead.
    'allowed_origins' => [
        'https://toanrobert.online',
        'https://www.toanrobert.online',
        'http://localhost:3000',
        'http://localhost:8080',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Must be true so the browser accepts the `Cookie` header that the
    // frontend sends with `credentials: 'include'` (used for JWT auth).
    'supports_credentials' => true,

];
