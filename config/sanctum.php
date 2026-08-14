<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Deliberately empty: this application's API is bearer-token only. The
    | React/Inertia frontend keeps authenticating with its session cookie over
    | the `web` routes and never calls /api/*.
    |
    | Naming a domain here would hand every browser session an authenticated,
    | cookie-carried path into the API — which means any XSS in the SPA could
    | drive the whole API as the logged-in user, and it would drag CSRF back
    | into scope for routes that have no CSRF protection. The API surface is
    | small precisely so it can stay reachable only by a credential someone
    | deliberately minted. Do not populate this without revisiting that.
    |
    */

    'stateful' => [],

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Empty for the same reason, and it is the load-bearing half: with a guard
    | listed here, Sanctum answers an API request from whatever first-party
    | session it can find before it ever looks for a bearer token. Empty means
    | there is exactly one way to authenticate against /api/* — a token.
    |
    */

    'guard' => [],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Null on purpose: expiry is per token, not global. Every token is issued
    | with its own `expires_at` (required at creation, see ApiTokensController)
    | so a caller can choose a short-lived token without shortening everyone
    | else's. A value here would silently override those per-token dates.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | A fixed, recognisable prefix so a leaked token is findable: GitHub's
    | secret scanning and most credential scanners key off exactly this kind
    | of marker, and it makes an accidentally-committed token greppable.
    |
    | Changing this does not invalidate existing tokens — the prefix is part
    | of the plaintext, not of the hash lookup.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'psend_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | Unused while `stateful` is empty — kept at the package defaults so the
    | file still matches upstream if statefulness is ever reconsidered.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
