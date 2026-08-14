<?php

declare(strict_types=1);

namespace App\Modules\Api\Events;

use App\Modules\Platform\Capabilities\Capability;
use Closure;
use InvalidArgumentException;

/**
 * The extension point through which cloud-modules and community-modules
 * add their own API endpoints.
 *
 * Core dispatches this once while loading routes/api.php, collects whatever
 * the listeners registered, and mounts each module itself — under a prefix
 * core chooses, inside the middleware stack core assembled, behind the
 * capability the module declared. A module supplies paths and controllers;
 * it does not get to say how they are authenticated.
 *
 * A package listens by *string* class name, never by importing this class:
 *
 *     Event::listen('App\Modules\Api\Events\RegisteringApiModules', function ($event): void {
 *         $event->register(
 *             slug: 'branding',
 *             routes: __DIR__.'/api-routes.php',
 *             capability: 'branding.customize',
 *         );
 *     });
 *
 * That indirection is what keeps the packages buildable and testable with
 * no host application present — the same constraint that ruled out an
 * interface or a shared base controller. See docs/extension-points-architecture.md.
 *
 * This is a guard against mistakes, not a sandbox. Nothing at runtime stops
 * a package from calling Route::post('api/v1/files/...') directly; what
 * stops it is ModuleRouteBoundaryTest, which is the same enforcement level
 * the rest of the extension system runs on.
 */
final class RegisteringApiModules
{
    /**
     * Keyed by slug so a collision is detectable rather than last-write-wins.
     *
     * @var array<string, ApiModule>
     */
    private array $modules = [];

    /**
     * @param  string  $slug  lowercase, hyphen-separated; becomes /api/v1/modules/{slug}
     * @param  string|Closure  $routes  path to a route file, or a closure declaring routes
     * @param  string|null  $capability  a Capability key, or null if the module is
     *                                   available in every edition. Required — not
     *                                   defaulted — so edition gating is a decision
     *                                   each module makes out loud rather than one it
     *                                   can forget. CustomAssets shipped routes that
     *                                   answered 200 in the wrong edition precisely
     *                                   because restating the gate was left to the
     *                                   package to remember.
     *
     * @throws InvalidArgumentException on a malformed slug, a duplicate slug, or an
     *                                  unknown capability key. Thrown at route-load
     *                                  time, so a misconfigured module fails loudly on
     *                                  the first request in development and in CI
     *                                  rather than silently not mounting.
     */
    public function register(string $slug, string|Closure $routes, ?string $capability): void
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 40) {
            throw new InvalidArgumentException(
                "Invalid API module slug [{$slug}]: expected lowercase letters, digits and single hyphens, at most 40 characters."
            );
        }

        if (isset($this->modules[$slug])) {
            throw new InvalidArgumentException(
                "API module slug [{$slug}] is already registered. Two packages cannot share a slug — one of them must rename."
            );
        }

        if (is_string($routes) && ! is_file($routes)) {
            throw new InvalidArgumentException(
                "API module [{$slug}] was registered with route file [{$routes}], which does not exist."
            );
        }

        if ($capability !== null && Capability::tryFrom($capability) === null) {
            throw new InvalidArgumentException(
                "API module [{$slug}] declared unknown capability [{$capability}]. Add the case to Capability first."
            );
        }

        $this->modules[$slug] = new ApiModule($slug, $routes, $capability);
    }

    /**
     * @return list<ApiModule>
     */
    public function modules(): array
    {
        return array_values($this->modules);
    }

    /**
     * The slugs an API client can expect to find under /api/v1/modules,
     * surfaced by GET /api/v1/me so a caller can discover what this
     * particular install offers before calling it.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->modules);
    }
}
