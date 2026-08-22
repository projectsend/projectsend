<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * The trusted proxy list has to be read from configuration, not from
 * bootstrap/app.php.
 *
 * The `withMiddleware` closure runs when the HTTP kernel is resolved, and
 * that happens *before* the dotenv bootstrapper reads .env. Anything set
 * only in .env is therefore invisible to `env()` in that closure, on every
 * web request — while artisan, which bootstraps in the other order, reports
 * the setting as working. That combination is what makes the bug so hard to
 * see from the outside: the operator sets TRUSTED_PROXIES, a CLI check
 * agrees it is set, and the web app ignores it anyway.
 *
 * With the proxy untrusted, Laravel falls back to the connecting address and
 * the plain scheme, so behind a TLS-terminating proxy every generated URL
 * and every redirect comes out as `http://` on a page the browser loaded
 * over `https://`. The browser then refuses to send the session cookie to
 * that other origin, the session looks empty, and the write fails with a 419
 * that reads as "your session expired".
 */
beforeEach(function () {
    Route::get('/__proxy-probe', fn () => response()->json([
        'root' => request()->getSchemeAndHttpHost(),
        'ip' => request()->ip(),
        'secure' => request()->isSecure(),
    ]));
});

test('forwarded scheme, host and client address are honoured when the proxy is trusted', function () {
    config()->set('trustedproxy.proxies', '*');

    $this->get('/__proxy-probe', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'files.example.com',
        'X-Forwarded-For' => '203.0.113.9',
    ])->assertOk()->assertJson([
        'root' => 'https://files.example.com',
        'ip' => '203.0.113.9',
        'secure' => true,
    ]);
});

test('forwarded headers are ignored when no proxy is trusted', function () {
    config()->set('trustedproxy.proxies', null);

    // Asserted against the forwarded values rather than a literal expected
    // host: the test environment's APP_URL supplies the host here, and
    // isSecure() is already true from it, so neither is a signal on its own.
    // What discriminates is that the proxy's claims are not adopted.
    $json = $this->get('/__proxy-probe', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'files.example.com',
        'X-Forwarded-For' => '203.0.113.9',
    ])->assertOk()->json();

    expect($json['ip'])->toBe('127.0.0.1')
        ->and($json['root'])->not->toContain('files.example.com');
});

test('the config key the framework falls back to is wired to TRUSTED_PROXIES', function () {
    // Illuminate\Http\Middleware\TrustProxies reads `trustedproxy.proxies`
    // when nothing called trustProxies(at:). That is the only path that sees
    // a value coming from .env, so the key has to stay spelled this way and
    // has to keep reading that variable. Evaluated directly rather than
    // through config(), which already holds the value loaded at boot.
    $_ENV['TRUSTED_PROXIES'] = '10.0.0.1,10.0.0.2';
    $_SERVER['TRUSTED_PROXIES'] = '10.0.0.1,10.0.0.2';

    try {
        expect(require base_path('config/trustedproxy.php'))
            ->toBe(['proxies' => '10.0.0.1,10.0.0.2']);
    } finally {
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    }
});

test('bootstrap/app.php does not read TRUSTED_PROXIES from the environment', function () {
    // Reintroducing this read is the regression: it works when the value is
    // a real environment variable (Docker `environment:`), and silently does
    // nothing when it comes from .env, which is the documented way to set it.
    expect(file_get_contents(base_path('bootstrap/app.php')))
        ->not->toContain('TRUSTED_PROXIES');
});
