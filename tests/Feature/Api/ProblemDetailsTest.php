<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->token = $this->staff->createToken('t', [Permission::Upload->value])->plainTextToken;
});

test('an unauthenticated API request is problem+json', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('type', 'unauthenticated')
        ->assertJsonPath('status', 401);
});

test('a missing API route is a problem+json 404', function () {
    $this->withToken($this->token)->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('type', 'not_found');
});

test('validation failures carry a machine-readable errors bag', function () {
    Route::middleware(['auth:sanctum', 'api-active', 'staff-token'])
        ->post('api/v1/_test/validating', function () {
            throw ValidationException::withMessages(['name' => 'The name field is required.']);
        });

    $this->withToken($this->token)->postJson('/api/v1/_test/validating')
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('type', 'validation_failed')
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

test('an unexpected failure never leaks its message when debug is off', function () {
    config(['app.debug' => false]);

    Route::middleware(['auth:sanctum', 'api-active', 'staff-token'])
        ->get('api/v1/_test/exploding', function () {
            throw new RuntimeException('connection string: mysql://root:hunter2@db/app');
        });

    $response = $this->withToken($this->token)->getJson('/api/v1/_test/exploding');

    $response->assertStatus(500)->assertJsonPath('type', 'server_error');
    expect($response->getContent())->not->toContain('hunter2');
});

test('a staff page that lives under the API prefix is not an API route', function () {
    // /api/docs and /api are pages from routes/web.php. Answering their
    // errors in problem+json told a signed-out visitor to send a Bearer
    // token, on a page whose whole purpose is to be read in a browser.
    $this->get('/api/docs')->assertRedirect('/login');
    $this->get('/api')->assertRedirect('/login');
});

test('web routes are untouched by the API error format', function () {
    // The renderer is scoped by path, not by whether the request accepts
    // JSON — Inertia requests do accept JSON, and reshaping their errors
    // would break the frontend's error handling.
    $response = $this->get('/definitely-not-a-route')->assertNotFound();

    expect($response->headers->get('content-type'))->not->toContain('problem+json');
});

test('a throttled request is a problem+json 429 that says when to retry', function () {
    // The throttle sets Retry-After and X-RateLimit-* as integers, and the
    // header bag only takes strings: copying them as they came turned every
    // 429 into a 500 with no Retry-After (#1831).
    config(['api.rate_limits' => [
        'community' => ['default' => 1, 'uploads' => 1],
        'cloud' => ['default' => 1, 'uploads' => 1],
    ]]);

    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk();

    $this->withToken($this->token)->getJson('/api/v1/me')
        ->assertStatus(429)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Limit', '1')
        ->assertJsonPath('status', 429);
});
