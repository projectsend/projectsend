<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The CSRF cookie, named after this installation instead of after Laravel.
 *
 * The framework hardcodes `XSRF-TOKEN`. Cookies are scoped by host and
 * ignore the port, so every Laravel application on one hostname writes
 * that same cookie — each holding its own session's token, encrypted with
 * its own key. Whichever answered a request most recently owns it.
 *
 * The failure that produces is genuinely confusing to diagnose: the
 * session is untouched and valid, so reads keep working, and only writes
 * fail — with a 419, which reads as "your session expired" when the
 * session is perfectly alive. Reloading appears to fix it, until the
 * neighbour answers another request. Any page that polls (this one polls
 * for unread notifications every thirty seconds) makes that window small
 * enough that writes fail almost every time.
 *
 * The session cookie is already named per installation, so this is that
 * decision finished rather than a new one: see `session.xsrf_cookie`.
 *
 * Only the cookie's *name* changes. The request header stays
 * `X-XSRF-TOKEN`, which is what the framework reads and what axios sends,
 * and header names do not collide between applications the way cookies do.
 */
class ValidateCsrfToken extends Middleware
{
    /**
     * @param  Request  $request
     * @param  array<string, mixed>  $config
     */
    protected function newCookie($request, $config): Cookie
    {
        return new Cookie(
            self::cookieName(),
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }

    /**
     * Shared with the Blade layout, which tells the frontend which cookie
     * to read — there is nothing on the client that could derive this.
     */
    public static function cookieName(): string
    {
        $name = config('session.xsrf_cookie');

        return is_string($name) && $name !== '' ? $name : 'XSRF-TOKEN';
    }
}
