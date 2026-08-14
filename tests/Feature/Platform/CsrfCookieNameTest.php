<?php

declare(strict_types=1);

use App\Http\Middleware\ValidateCsrfToken;
use App\Models\User;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The CSRF cookie is named after this installation, not after the
 * framework.
 *
 * Cookies are scoped by host and ignore the port, so two Laravel
 * applications on one hostname share every cookie name they have in
 * common. With the framework's hardcoded `XSRF-TOKEN` that means a
 * staging copy on another port — or any neighbouring Laravel app —
 * overwrites this installation's token, and every write here starts
 * failing with a 419.
 *
 * That failure is horrible to diagnose from the inside: the session is
 * untouched, so reads keep working and only writes break, and a 419 reads
 * as "your session expired" when the session is perfectly alive.
 * Reloading appears to fix it, right up until the neighbour answers
 * another request.
 */
beforeEach(function () {
    User::factory()->create();
});

test('the csrf cookie is named after this installation', function () {
    config()->set('session.xsrf_cookie', 'projectsend_cloud_xsrf');

    $response = $this->get('/login');

    // getName(), not pluck('name') — Symfony's Cookie exposes a method, and
    // plucking a property that isn't there yields a list of nulls that
    // quietly fails to contain anything at all.
    $names = array_map(
        static fn (Cookie $cookie): string => $cookie->getName(),
        $response->headers->getCookies(),
    );

    expect($names)
        ->toContain('projectsend_cloud_xsrf')
        // The name the framework would have used, and the whole problem.
        ->not->toContain('XSRF-TOKEN');
});

test('the name is derived from the session cookie, so nobody has to set two', function () {
    // config/session.php derives it; this asserts the shape that derivation
    // produces rather than re-deriving it here.
    expect(config('session.xsrf_cookie'))
        ->toBe(str_replace('_session', '', (string) config('session.cookie')).'_xsrf');
});

test('it falls back to the framework name rather than an empty cookie', function () {
    config()->set('session.xsrf_cookie', '');

    expect(ValidateCsrfToken::cookieName())->toBe('XSRF-TOKEN');
});

test('the layout tells the frontend which cookie to read', function () {
    config()->set('session.xsrf_cookie', 'projectsend_cloud_xsrf');

    // Nothing on the client can derive this name, so the page has to carry
    // it — and if the tag and the cookie ever disagree, every write 419s.
    $this->get('/login')
        ->assertOk()
        ->assertSee('<meta name="xsrf-cookie" content="projectsend_cloud_xsrf">', false);
});
