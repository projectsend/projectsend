<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Cross-origin access is off by default
|--------------------------------------------------------------------------
|
| Laravel ships `allowed_origins => ['*']` for `api/*`. That was never
| dangerous here — bearer-only auth, `supports_credentials` false, no
| browser holds a token — but it grants something no consumer needs:
| server-side automation and native apps are not subject to CORS at all.
| config/cors.php narrows it to whatever API_ALLOWED_ORIGINS names, which
| is nothing unless a self-hoster opts in.
|
*/

beforeEach(function () {
    User::factory()->create();
});

test('an API response carries no allow-origin header by default', function () {
    $response = $this->withHeaders(['Origin' => 'https://evil.example.com'])
        ->getJson('/api/v1/me');

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

test('a preflight from an unlisted origin is not granted', function () {
    $response = $this->call('OPTIONS', '/api/v1/me', server: [
        'HTTP_ORIGIN' => 'https://evil.example.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

test('a named origin is granted', function () {
    // The shape a self-hoster gets by setting API_ALLOWED_ORIGINS.
    config(['cors.allowed_origins' => ['https://app.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://app.example.com'])
        ->getJson('/api/v1/me');

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://app.example.com');
});

test('credentials are never allowed cross-origin', function () {
    // Cookies must not travel to this API from anywhere — the same
    // decision config/sanctum.php makes by leaving `stateful` empty.
    config(['cors.allowed_origins' => ['https://app.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://app.example.com'])
        ->getJson('/api/v1/me');

    expect($response->headers->get('Access-Control-Allow-Credentials'))->toBeNull()
        ->and(config('cors.supports_credentials'))->toBeFalse();
});

test('rate limit headers are readable by a browser client', function () {
    config(['cors.allowed_origins' => ['https://app.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://app.example.com'])
        ->getJson('/api/v1/me');

    // Without these exposed, a browser client can read the body but not
    // how much allowance is left or when to retry.
    expect($response->headers->get('Access-Control-Expose-Headers'))
        ->toContain('X-RateLimit-Remaining');
});

test('web routes are outside the CORS policy entirely', function () {
    $response = $this->withHeaders(['Origin' => 'https://app.example.com'])->get('/login');

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});
