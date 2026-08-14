<?php

declare(strict_types=1);

namespace App\Modules\Api\Events;

use Closure;

/**
 * One registered module's API surface. Value object, built only by
 * RegisteringApiModules::register() so the invariants it validates are the
 * only way to produce one.
 */
final class ApiModule
{
    /**
     * @param  string  $slug  URL segment and route-name segment: /api/v1/modules/{slug}
     * @param  string|Closure  $routes  route file path, or a closure that declares routes
     * @param  string|null  $capability  Capability key gating the whole group, null if edition-agnostic
     */
    public function __construct(
        public readonly string $slug,
        public readonly string|Closure $routes,
        public readonly ?string $capability,
    ) {}
}
