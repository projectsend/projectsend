<?php

declare(strict_types=1);

namespace App\Modules\Api\Auth;

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Support\Facades\Route as Router;

/**
 * Which abilities a given user may actually attach to a token, here, now.
 *
 * Three independent constraints, all of which must hold:
 *
 *  1. **Permission** — what this user's role grants. A token must never be
 *     a way to acquire an ability its owner does not have.
 *  2. **Capability** — what this edition has at all. `manage_users` on a
 *     cloud install is not a permission the user is merely prevented from
 *     using; the feature is absent, and every route behind it 404s.
 *  3. **Implemented** — whether any API route actually consumes the
 *     ability. The API covers a fraction of what the web UI does, and
 *     offering a checkbox for `manage_groups` before a groups endpoint
 *     exists invites someone to grant an ability that silently does
 *     nothing. A permission is not an API ability until a route asks for it.
 *
 * The third is derived from the routes rather than kept as a list here,
 * which is the point: `token-can:` on each API route is already the
 * authoritative statement of what that endpoint needs, so a new endpoint
 * makes its abilities selectable the moment it is registered, and no
 * parallel list can fall out of step. Package routes registered through
 * RegisteringApiModules are covered by the same scan.
 *
 * Deliberately not memoised across resolutions: capability comes from a
 * registry bound per resolution so tests can flip the edition, and the
 * permission side is already memoised inside PermissionChecker. The route
 * scan is memoised per instance since the route table cannot change
 * mid-request.
 */
class TokenAbilities
{
    /** @var list<string>|null */
    private ?array $inUse = null;

    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * Every ability key this user may be granted, as strings.
     *
     * @return list<string>
     */
    public function availableFor(User $user): array
    {
        $granted = $this->permissions->grantedKeys($user);

        return array_values(array_filter(
            $granted,
            fn (string $key): bool => $this->isAvailable($key) && $this->isImplemented($key),
        ));
    }

    /**
     * The same list as Permission cases, for a UI that needs labels and
     * categories rather than bare keys.
     *
     * @return list<Permission>
     */
    public function casesFor(User $user): array
    {
        $available = $this->availableFor($user);

        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => in_array($permission->value, $available, true),
        ));
    }

    /**
     * Whether the ability is usable in this edition at all, ignoring who is
     * asking. An unknown key is not available — a token may only ever carry
     * abilities drawn from the Permission vocabulary.
     *
     * Note this does NOT consider whether an endpoint exists: it is the
     * check EnsureTokenCan makes, and there the question is already settled
     * — a route asking for an ability is itself the endpoint.
     */
    public function isAvailable(string $key): bool
    {
        $permission = Permission::tryFrom($key);

        if ($permission === null) {
            return false;
        }

        $capability = $permission->capability();

        return $capability === null || $this->capabilities->has($capability);
    }

    /**
     * Whether any API endpoint consumes this ability today.
     */
    public function isImplemented(string $key): bool
    {
        return in_array($key, $this->inUse(), true);
    }

    /**
     * Abilities named by a `token-can:` middleware on any registered
     * /api/v1 route — core or module.
     *
     * Read off the route table rather than declared in a list, so this can
     * never disagree with what the endpoints actually require. Route
     * caching preserves middleware, so it is correct on a cached install
     * too.
     *
     * @return list<string>
     */
    public function inUse(): array
    {
        if ($this->inUse !== null) {
            return $this->inUse;
        }

        $abilities = [];

        foreach (Router::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'token-can:')) {
                    continue;
                }

                foreach (explode(',', substr($middleware, strlen('token-can:'))) as $ability) {
                    $ability = trim($ability);

                    if ($ability !== '') {
                        $abilities[$ability] = true;
                    }
                }
            }
        }

        return $this->inUse = array_keys($abilities);
    }
}
