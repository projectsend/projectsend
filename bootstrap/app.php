<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ValidateCsrfToken;
use App\Modules\Api\Http\Middleware\EnsureApiAccountIsActive;
use App\Modules\Api\Http\Middleware\EnsureStaffToken;
use App\Modules\Api\Http\Middleware\EnsureTokenCan;
use App\Modules\Api\Http\Middleware\RecordApiRequest;
use App\Modules\Api\Http\Middleware\SetApiLocale;
use App\Modules\Api\Support\ProblemDetails;
use App\Modules\Identity\Http\Middleware\EnforceTwoFactor;
use App\Modules\Identity\Http\Middleware\EnsureAccountIsActive;
use App\Modules\Identity\Http\Middleware\EnsureSetupIsComplete;
use App\Modules\Identity\Http\Middleware\EnsureStaff;
use App\Modules\Platform\Http\Middleware\EnsureCapability;
use App\Modules\Platform\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Versioned at the prefix, not with a header or a query parameter:
        // /api/v1 is a frozen contract, and a future /api/v2 gets its own
        // route file rather than branching inside these controllers.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Unset = trust nothing, which is correct for the shipped topology
        // (nginx talks to PHP-FPM directly and passes the real REMOTE_ADDR).
        // Behind anything else — a load balancer, Cloudflare, the hosted
        // Cloud ingress — this MUST name the proxy, or every client appears
        // to come from it: per-IP throttles collapse into one shared bucket
        // and the download IP log records the proxy instead of the client.
        $proxies = env('TRUSTED_PROXIES');

        if (is_string($proxies) && $proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        $middleware->web(append: [
            // Binds every session to the password hash it was created under,
            // so changing a password (or a reset) actually terminates the
            // account's other sessions instead of leaving a stolen one live.
            // Required for Auth::logoutOtherDevices() to have any effect.
            AuthenticateSession::class,
            EnsureSetupIsComplete::class,
            EnsureAccountIsActive::class,
            EnforceTwoFactor::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            // Deliberately NOT here: AddLinkHeadersForPreloadedAssets. It
            // copies every Vite preload into a `Link:` response header,
            // and the head of the document already carries the identical
            // tags — twenty of them on the login page, more on a heavier
            // one. The copy is what a browser never reads and a proxy has
            // to buffer: it pushed /files past 6 KB of headers, where the
            // 4 KB proxy_buffer_size that nginx, and therefore Nginx Proxy
            // Manager, defaults to answers 502. Some pages fit and some do
            // not, so it reads as an intermittent fault rather than a
            // header that is always too big (#1664). Nothing is lost but
            // 103 Early Hints, which this application does not send.
        ]);

        // The API group gets none of the web stack above — no session, no
        // CSRF, no Inertia. Locale is the one thing worth carrying over,
        // since validation messages are written for a human to read; the
        // web SetLocale can't be reused because it reads the session.
        $middleware->api(append: [
            SetApiLocale::class,
            // Applied to the group rather than per route, so an endpoint
            // added later is measured without anyone opting in.
            RecordApiRequest::class,
        ]);

        $middleware->throttleApi();

        $middleware->validateCsrfTokens(except: [
            'uploads/*/parts/*',
        ]);

        // Swapped for the subclass only to name the CSRF cookie after this
        // installation rather than after the framework — see that class for
        // what sharing `XSRF-TOKEN` with a neighbouring app does.
        //
        // `web(replace:)` rather than the bare `replace()`: the latter only
        // reaches the global stack, and CSRF lives in the web group, so it
        // silently does nothing here.
        $middleware->web(replace: [
            Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => ValidateCsrfToken::class,
        ]);

        $middleware->alias([
            'capability' => EnsureCapability::class,
            'staff' => EnsureStaff::class,
            'staff-token' => EnsureStaffToken::class,
            'token-can' => EnsureTokenCan::class,
            'api-active' => EnsureApiAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // RFC 7807 for /api/* only. Everything else — web pages, Inertia
        // requests, the public share links — keeps Laravel's own handling
        // untouched, which is why this is scoped by path rather than by
        // whether the request happens to accept JSON (Inertia requests do).
        $exceptions->render(function (Throwable $e, Request $request) {
            $problems = app(ProblemDetails::class);

            return $problems->shouldHandle($request)
                ? $problems->render($request, $e)
                : null;
        });
    })->create();
