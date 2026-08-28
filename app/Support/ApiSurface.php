<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Whether a request is on the API rather than on the web site.
 *
 * Two questions used to answer this, and both answer something else. A
 * path test alone (`api/*`) is wrong because two staff *pages* live under
 * that prefix — the API dashboard at /api and the OpenAPI reference at
 * /api/docs, both registered in routes/web.php — and their errors belong
 * to the web site: a signed-out visitor to /api/docs wants the login
 * redirect, not a 401 telling them to send a Bearer token. An
 * `expectsJson()` test is wrong because the Accept header is the caller's
 * preference, not a property of the route: whether an endpoint exists in
 * this edition cannot depend on what the caller is willing to parse.
 *
 * So: under the API prefix, and not part of the `web` middleware group.
 * The group is what actually separates the two — sessions, cookies and
 * CSRF on one side, tokens on the other — and it stays right for a future
 * /api/v2 without this being edited.
 *
 * An unmatched path has no route to ask, and that is the API's answer:
 * a request to a URL under the API prefix that resolves to nothing is a
 * 404 the API should describe in its own error format.
 */
class ApiSurface
{
    public static function matches(Request $request): bool
    {
        if (! $request->is('api/*')) {
            return false;
        }

        return ! in_array('web', $request->route()?->middleware() ?? [], true);
    }
}
