<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The token twin of Identity's EnsureAccountIsActive: deactivating an
 * account revokes its API access on the very next request, without anyone
 * having to hunt down the tokens it minted.
 *
 * Deleted accounts need no equivalent — users are soft-deleted and the
 * default query scope means Sanctum simply fails to resolve the tokenable,
 * which surfaces as a 401.
 *
 * Unlike the web version there is no session to tear down and nowhere to
 * redirect to, so this is a flat 401: the credential is no longer good.
 */
class EnsureApiAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->active) {
            abort(401);
        }

        return $next($request);
    }
}
