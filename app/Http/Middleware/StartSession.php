<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as FrameworkStartSession;
use Illuminate\Contracts\Session\Session;

/**
 * The framework's session middleware, except that a request asking for
 * JSON is never remembered as "the previous page".
 *
 * `back()` prefers the Referer header and falls back to the URL the
 * session recorded last. Laravel records every GET not marked as Ajax,
 * and a plain fetch() is not marked. So the notification bell's poll for
 * its unread count became the previous page. Wherever the Referer did not
 * arrive, because a proxy or a browser stripped it, saving any settings
 * form redirected to /notifications/unread-count, and Inertia showed the
 * raw {"count":0} in an error dialog (#1799).
 *
 * The rule is about the request, not about that one route: nothing that
 * asked for JSON is a page anybody goes back to. Inertia visits ask for
 * HTML, so they are recorded exactly as before.
 */
class StartSession extends FrameworkStartSession
{
    protected function storeCurrentUrl(Request $request, $session): void
    {
        if ($request->wantsJson()) {
            return;
        }

        /** @var Session $session */
        parent::storeCurrentUrl($request, $session);
    }
}
