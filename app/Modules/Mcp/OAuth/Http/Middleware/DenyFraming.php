<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The consent screen, never inside another site's frame.
 *
 * The shipped nginx configuration already says so for every page, but an
 * install behind Apache or another server may not. This is the one page
 * where a click decides what an outside program may do with the account,
 * so it says so itself.
 */
class DenyFraming
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");

        return $response;
    }
}
