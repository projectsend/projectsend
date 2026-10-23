<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The discovery documents and the token endpoints are read by programs
 * on other origins — an assistant running in a browser among them — and
 * a public client has nothing a cross-origin read could leak.
 */
class AllowCrossOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $request->isMethod('OPTIONS') ? response()->noContent() : $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, MCP-Protocol-Version');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
