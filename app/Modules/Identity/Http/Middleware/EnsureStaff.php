<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff surfaces require a staff account, always. Permissions refine
 * what staff may do there — they can NEVER admit a client, no matter
 * what keys an administrator grants the Client role (e.g. "upload"
 * for portal uploads must not open the staff file manager).
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isStaff()) {
            return $next($request);
        }

        // A client navigating to a staff URL (stale intended-redirects
        // after login, old bookmarks) is sent home rather than shown an
        // error — staff pages are simply not part of their world.
        // Mutations stay a hard 403.
        if ($user !== null && $request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->route('dashboard');
        }

        abort(403);
    }
}
