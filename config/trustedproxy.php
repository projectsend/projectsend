<?php

// Read here rather than in bootstrap/app.php's withMiddleware closure: that
// closure runs when the HTTP kernel is resolved, which under PHP-FPM is
// BEFORE the dotenv bootstrapper loads .env, so env('TRUSTED_PROXIES') is
// null there on every web request (it works in artisan, which bootstraps
// first — the discrepancy is invisible in CLI testing). Config files load
// after dotenv, and the framework's TrustProxies middleware falls back to
// this key on its own.
return [
    'proxies' => env('TRUSTED_PROXIES'),
];
