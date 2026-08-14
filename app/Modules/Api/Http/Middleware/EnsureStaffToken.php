<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API is staff-only in v1.
 *
 * Client accounts have no way to mint a token (the settings page that
 * issues them is behind `staff`), so this is the second half of a belt-and
 * -braces pair rather than the only control: if a client ever ends up
 * holding a token — a seeded fixture, a support script, an account whose
 * type was changed after issuance — every API route still refuses it.
 *
 * The reason clients are excluded is scope, not capability: a token acting
 * for a client is the largest privacy surface this API could have, and it
 * needs its own ability vocabulary before it can exist safely. Until that
 * is built, it simply doesn't.
 */
class EnsureStaffToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->isStaff()) {
            abort(403);
        }

        return $next($request);
    }
}
