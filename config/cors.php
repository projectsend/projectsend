<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    |
    | Published to replace Laravel's default, which answers every /api/*
    | request with `Access-Control-Allow-Origin: *`.
    |
    | That default was not dangerous here — the API is bearer-token only,
    | `supports_credentials` is false, and no browser holds a token — but it
    | was inherited rather than chosen, and it grants something nothing
    | needs: the API's consumers are server-side automation and native
    | mobile apps, none of which are subject to CORS at all. A wildcard buys
    | those callers nothing while leaving a browser-reachable door open for
    | whatever the API grows into later.
    |
    | So the default here is *no* cross-origin browser access. A self-hoster
    | building a browser front-end against their own install names their
    | origin explicitly:
    |
    |     API_ALLOWED_ORIGINS=https://app.example.com,https://staging.example.com
    |
    | Should credentialed cross-origin requests ever be wanted, note that
    | `supports_credentials => true` may not be combined with a wildcard
    | origin — which is the browser telling you to name the origins anyway.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(
        explode(',', (string) env('API_ALLOWED_ORIGINS', '')),
        static fn (string $origin): bool => trim($origin) !== '',
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Without this a browser client can read the body but not the headers
    // that tell it how much allowance is left or when to retry — the two
    // things a well-behaved client needs in order to back off.
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 0,

    // Bearer tokens only: cookies must never travel cross-origin to this
    // API. See config/sanctum.php, where both `stateful` and `guard` are
    // empty for the same reason.
    'supports_credentials' => false,

];
