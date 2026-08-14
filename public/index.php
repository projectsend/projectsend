<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Refuse to start with a clear message — rather than a stack trace or a bare
// 500 — when the environment has never been configured. Runs before the
// autoloader and the framework, on purpose: it must still work when those
// are among the things not yet in place.
require __DIR__.'/../bootstrap/preflight.php';

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
