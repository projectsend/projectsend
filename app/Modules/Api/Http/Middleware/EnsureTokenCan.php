<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Auth\TokenAbilities;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level ability enforcement: `->middleware('token-can:edit_files,edit_others_files')`.
 * Any one of the listed abilities is enough, matching how the web routes
 * treat own/others permission pairs.
 *
 * Why this exists instead of Sanctum's stock `ability` middleware: Sanctum
 * only asks what was baked into the token when it was minted. Roles change.
 * A staff member who held `delete_files` in March, minted a token, and was
 * moved to a read-only role in April still carries a token that claims the
 * ability — and stock Sanctum honours the claim. Every request here checks
 * both halves:
 *
 *   1. the token was granted the ability, and
 *   2. the user still holds the underlying permission right now.
 *
 * The Gate side is the live one, registered per Permission case in
 * IdentityServiceProvider, so demotion takes effect on the next request
 * rather than whenever someone remembers to revoke the token.
 *
 * This is an additional gate, never a substitute for the domain's own
 * policy checks — controllers still call Gate::authorize() on the model.
 */
class EnsureTokenCan
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $available = app(TokenAbilities::class);

        foreach ($abilities as $ability) {
            // Three checks, all of which must hold:
            //
            //  - the edition has the feature at all (a token minted on a
            //    community install and carried to a cloud one must not keep
            //    working — route-level `capability:` middleware is the
            //    primary gate, this is the belt-and-braces half);
            //  - the token was granted the ability;
            //  - the owner still holds the permission today.
            //
            // tokenCan() is false when there is no access token — a
            // first-party session, which config/sanctum.php makes
            // unreachable here but which must fail closed if it ever
            // becomes reachable: "proved no ability", not "may do anything".
            if ($available->isAvailable($ability)
                && $user->tokenCan($ability)
                && Gate::forUser($user)->allows($ability)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
