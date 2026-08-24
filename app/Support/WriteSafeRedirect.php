<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A redirect answering a write has to be a 303, not a 302.
 *
 * A browser follows a 302 by replaying the request method on the new
 * location — POST is the only one it downgrades to GET. So a PUT that
 * gets redirected to the login page is replayed as `PUT /login`, which
 * accepts only GET and POST, and the person is shown a 405 instead of
 * the one thing they needed to read: sign in again. 303 means "see
 * other, and follow it with GET", which is the only sensible next step
 * after a write.
 *
 * Inertia's own middleware already does this for responses that pass
 * back through it. Two kinds never do, which is the whole reason this
 * exists:
 *
 *  - A redirect rendered during **exception handling** — the guest
 *    redirect after AuthenticationException above all — never travels
 *    back through the middleware stack at all.
 *  - A redirect returned early by middleware that runs *before*
 *    HandleInertiaRequests: EnsureSetupIsComplete, EnsureAccountIsActive
 *    and EnforceTwoFactor. A response only unwinds through middleware it
 *    already entered, and those three answer before Inertia's is reached.
 *
 * Reads are left alone. A 302 answering a GET is correct, and replaying
 * a GET is exactly the right thing to do.
 *
 * See issue #1673 and pull request #1680, which found and fixed the
 * first of the two cases; this is the same rule, kept in one place so
 * the second could not drift from it.
 */
final class WriteSafeRedirect
{
    /**
     * The methods a browser replays verbatim when following a 302.
     *
     * POST is deliberately absent: browsers already downgrade it to GET,
     * which is why form posts never showed this bug and only the
     * Inertia-style PUT/PATCH/DELETE saves did.
     */
    private const REPLAYED_METHODS = ['PUT', 'PATCH', 'DELETE'];

    public static function apply(Request $request, Response $response): Response
    {
        if ($response->getStatusCode() === Response::HTTP_FOUND
            && in_array($request->method(), self::REPLAYED_METHODS, true)) {
            $response->setStatusCode(Response::HTTP_SEE_OTHER);
        }

        return $response;
    }
}
