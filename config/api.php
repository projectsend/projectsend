<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Requests per minute, keyed by access token (falling back to IP for
    | unauthenticated hits). Differentiated by edition only: there are no
    | billing or plan tiers in this application to key off, and inventing
    | one here would be a claim the rest of the codebase can't back up.
    |
    | `uploads` is deliberately far lower than `default` — an upload costs
    | orders of magnitude more disk, CPU and bandwidth than a list call,
    | so it gets its own bucket rather than sharing the general allowance.
    |
    */

    'rate_limits' => [
        'community' => [
            'default' => 60,
            'uploads' => 20,
        ],
        'cloud' => [
            'default' => 120,
            'uploads' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token lifetime (days)
    |--------------------------------------------------------------------------
    |
    | `default` pre-fills the expiry field when minting a token; `max` is the
    | ceiling the request validator enforces. A token is a password that
    | never gets rotated by habit, so an expiry is required at creation
    | (see ApiTokensController) and this is how long it may run for.
    |
    */

    'tokens' => [
        'default_days' => 90,
        'max_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Cursor page size for every list endpoint. `max` caps what a caller may
    | ask for via ?per_page, so one client cannot turn a list call into a
    | full-table export.
    |
    */

    'pagination' => [
        'per_page' => 25,
        'max_per_page' => 100,
    ],

];
