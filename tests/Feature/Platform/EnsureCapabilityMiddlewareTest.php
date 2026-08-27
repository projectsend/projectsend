<?php

declare(strict_types=1);

use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // storage.configure rather than users.manage: this test needs a
    // capability that is genuinely Community-only, and users.manage stopped
    // being one in 2.2.0 when a platform's seats became a cap rather than a
    // closed screen. Any Community-only key would do — this one is picked
    // because a managed installation is given its storage, which is the
    // clearest example of the edition line the middleware exists to draw.
    Route::middleware('capability:storage.configure')->get('/test/community-only', fn () => 'ok');
    Route::middleware('capability:branding.customize')->get('/test/cloud-only', fn () => 'ok');

    // Under api/, because ProblemDetails is scoped to the API on purpose —
    // a refusal on a web route is not supposed to be an RFC 7807 document.
    // Not api/v1/, so OpenApiContractTest's documented-vs-registered
    // comparison ignores it.
    Route::middleware('capability:branding.customize')->get('api/test/cloud-only', fn () => 'ok');
});

test('a capability available in the current edition lets the request through', function () {
    config()->set('projectsend.edition', Edition::Community);

    $this->get('/test/community-only')->assertOk()->assertSee('ok');
});

test('a capability unavailable in the current edition returns 404 on web requests', function () {
    config()->set('projectsend.edition', Edition::Community);

    $this->get('/test/cloud-only')->assertNotFound();
});

// The machine-readable half survives, but as an RFC 7807 document like
// every other API error rather than a shape of its own — a caller that
// parses errors once should not have to special-case this one. `type` is
// the slug to branch on; `capability` and `edition` say which feature and
// where, which is the part worth giving up on rather than retrying.
test('a capability unavailable in the current edition returns a machine-readable 403 on API requests', function () {
    config()->set('projectsend.edition', Edition::Community);

    $this->getJson('/api/test/cloud-only')
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson([
            'type' => 'capability_unavailable',
            'status' => 403,
            'capability' => 'branding.customize',
            'edition' => 'community',
        ]);
});

test('the same routes flip availability when running as the cloud edition', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $this->get('/test/cloud-only')->assertOk();
    $this->get('/test/community-only')->assertNotFound();
});
