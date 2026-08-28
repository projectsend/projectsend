<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Api\Events\RegisteringApiModules;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The frame core holds around module-provided endpoints
|--------------------------------------------------------------------------
|
| cloud-modules and community-modules register their API routes through
| RegisteringApiModules. These assert the constraints core applies on their
| behalf, and that nothing can register outside the space reserved for it.
|
*/

beforeEach(function () {
    User::factory()->create();
});

test('a module slug must be well formed', function (string $slug) {
    $event = new RegisteringApiModules;

    expect(fn () => $event->register($slug, fn () => null, null))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'uppercase' => 'Branding',
    'underscore' => 'custom_assets',
    'path traversal' => '../../etc',
    'slash' => 'a/b',
    'leading hyphen' => '-branding',
    'empty' => '',
]);

test('two modules cannot share a slug', function () {
    $event = new RegisteringApiModules;
    $event->register('branding', fn () => null, null);

    expect(fn () => $event->register('branding', fn () => null, null))
        ->toThrow(InvalidArgumentException::class);
});

test('a module cannot declare a capability core does not know', function () {
    $event = new RegisteringApiModules;

    expect(fn () => $event->register('branding', fn () => null, 'invented.capability'))
        ->toThrow(InvalidArgumentException::class);
});

test('a missing route file is rejected at registration', function () {
    $event = new RegisteringApiModules;

    expect(fn () => $event->register('branding', '/nonexistent/api-routes.php', null))
        ->toThrow(InvalidArgumentException::class);
});

test('registered modules are reported for discovery', function () {
    $event = new RegisteringApiModules;
    $event->register('branding', fn () => null, 'branding.customize');
    $event->register('custom-assets', fn () => null, 'custom_assets.manage');

    expect($event->slugs())->toBe(['branding', 'custom-assets']);
});

/*
 * The boundary itself: a package may not claim a path under /api/v1
 * except beneath /api/v1/modules. Without this a package could shadow or
 * extend a core endpoint and inherit its trust.
 *
 * Keyed off the controller's namespace rather than a hardcoded list of
 * core URIs — the packages are the thing being constrained, and a list of
 * core routes would need editing every time core gains an endpoint, which
 * makes it a chore that gets rubber-stamped rather than a guard.
 */
test('no package route escapes the modules prefix', function () {
    $packageNamespace = 'ProjectSend\\';

    $offenders = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->reject(fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'api/v1/modules/'))
        ->filter(function (RouteInstance $route) use ($packageNamespace): bool {
            $action = $route->getAction('controller') ?? '';

            return is_string($action) && str_starts_with($action, $packageNamespace);
        })
        ->map(fn (RouteInstance $route): string => $route->uri())
        ->unique()
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

/*
 * The same boundary, from outside /api/v1.
 *
 * The test above only looks at paths beginning `api/v1/`, so a package
 * claiming `/platform/v1` passed it — not because that was sanctioned,
 * but because nothing was looking.
 *
 * What is policed here is *machine* surfaces: roots a non-browser caller
 * authenticates to. A package route there is a package extending the
 * host's trusted perimeter rather than plugging into it. Package web
 * screens are deliberately not policed — community-modules' Custom
 * Assets adds `system/settings/custom-assets`, that is the point of a
 * module, and those go through the host's session and capability
 * middleware in plain sight. Listing them here would be the hardcoded
 * URI list the test above explains it is avoiding.
 *
 * `platform/v1` is a single documented exception, in the same shape as
 * the openapi.json allowlist below: a control plane is not an
 * integration and does not belong under the token-authenticated API.
 * Adding a second exception has to be a deliberate edit here, with a
 * reason beside it.
 */
test('a package claims no machine surface outside the two it is given', function () {
    $packageNamespace = 'ProjectSend\\';

    // Roots something other than a browser authenticates to.
    $machineRoots = ['api/', 'platform/'];

    $sanctioned = [
        'api/v1/modules/',
        // The Cloud control plane. Deliberately outside /api/v1: it is
        // authenticated by a platform secret rather than a staff bearer
        // token, so putting it under the API's stack would mean either
        // loosening that stack or pretending the caller is a person.
        'platform/v1/',
    ];

    $offenders = collect(Route::getRoutes()->getRoutes())
        ->filter(function (RouteInstance $route) use ($packageNamespace): bool {
            $action = $route->getAction('controller') ?? '';

            return is_string($action) && str_starts_with($action, $packageNamespace);
        })
        ->filter(fn (RouteInstance $route): bool => collect($machineRoots)
            ->contains(fn (string $root): bool => str_starts_with($route->uri(), $root)))
        ->reject(fn (RouteInstance $route): bool => collect($sanctioned)
            ->contains(fn (string $prefix): bool => str_starts_with($route->uri(), $prefix)))
        ->map(fn (RouteInstance $route): string => $route->uri())
        ->unique()
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('core api routes all carry the staff token stack', function () {
    // The other half of the same boundary: whatever core adds under
    // /api/v1, it must not be reachable without a staff token. A new
    // endpoint declared outside the group in routes/api.php would be.
    //
    // The allowlist is a single documented exception rather than a loosened
    // rule: adding a second unauthenticated endpoint has to be a deliberate
    // edit here, with a reason next to it.
    $publicByDesign = [
        // The specification itself — a client needs it before it can have a
        // token, and it describes the API's shape, never this
        // installation's data (asserted in OpenApiContractTest).
        'api/v1/openapi.json',
    ];

    $unguarded = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->reject(fn (RouteInstance $route): bool => in_array($route->uri(), $publicByDesign, true))
        ->reject(fn (RouteInstance $route): bool => in_array('staff-token', $route->gatherMiddleware(), true))
        ->map(fn (RouteInstance $route): string => $route->uri())
        ->unique()
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

/*
 * With a real module installed, the edition gate must actually bite.
 *
 * Physical absence is the primary control — cloud-modules is simply not
 * installed on a community deployment — but "present anyway" is the case
 * that has gone wrong before: CustomAssets once shipped routes that
 * answered 200 in an edition where the feature was never supposed to
 * exist, because restating the capability was left to the package. Here
 * the host applies it from the module's own declaration, so it cannot be
 * forgotten.
 *
 * Skipped where no module is installed, which includes CI.
 */
test('a module endpoint is refused in an edition without its capability', function () {
    $moduleRoute = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'api/v1/modules/'));

    if ($moduleRoute === null) {
        expect(true)->toBeTrue();

        return;
    }

    // Read the capability off the route rather than naming one. This used
    // to assert "branding is cloud-only, and the suite runs as community",
    // which stopped being true when branding moved into core on
    // 2026-08-28 — and the assumption was never what the test was for. The
    // invariant is that a module endpoint is refused when its capability
    // is absent, whichever module happens to be installed and whichever
    // capability it declares.
    $capability = collect($moduleRoute->gatherMiddleware())
        ->map(fn (mixed $middleware): string => is_string($middleware) ? $middleware : '')
        ->first(fn (string $middleware): bool => str_starts_with($middleware, 'capability:'));

    expect($capability)->not->toBeNull('a module route with no capability gate is the bug this file exists to catch');

    $key = substr((string) $capability, strlen('capability:'));

    $staff = User::factory()->create();
    $token = $staff->createToken('t', ['edit_settings'])->plainTextToken;

    // Taken away from an installation that would otherwise hold it, which
    // is how a hosted plan withholds one — see CapabilityRegistry.
    config(['projectsend.edition' => Edition::Cloud, 'projectsend.capabilities_disabled' => $key]);
    forgetRequestState();

    $this->withToken($token)->getJson('/'.$moduleRoute->uri())->assertForbidden();

    config(['projectsend.capabilities_disabled' => null]);
    forgetRequestState();

    $this->withToken($token)->getJson('/'.$moduleRoute->uri())->assertOk();
});

test('every module route inherits the core auth stack', function () {
    $moduleRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'api/v1/modules/'));

    if ($moduleRoutes->isEmpty()) {
        // Neither package is installed in this checkout. Skipping is
        // correct — the invariant is about installed modules, and a
        // failure here would just mean "no packages present".
        expect(true)->toBeTrue();

        return;
    }

    $moduleRoutes->each(function (RouteInstance $route): void {
        $middleware = $route->gatherMiddleware();

        expect($middleware)->toContain('auth:sanctum')
            ->and($middleware)->toContain('staff-token')
            ->and($middleware)->toContain('api-active');
    });
});
