<?php

declare(strict_types=1);

namespace App\Modules\Api;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * Which module endpoints this install actually exposes, for GET /api/v1/me.
 *
 * Derived from the registered routes rather than remembered from the
 * RegisteringApiModules dispatch, because that dispatch does not happen on
 * a route-cached install: `route:cache` loads routes from the cache file
 * and never executes routes/api.php. Route *names* survive caching, so
 * reading them back is the one source that is correct in both cases.
 */
class ApiModuleRegistry
{
    /** @var list<string>|null */
    private ?array $slugs = null;

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        if ($this->slugs !== null) {
            return $this->slugs;
        }

        $slugs = [];

        foreach (Router::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'api.modules.')) {
                continue;
            }

            $slug = explode('.', substr($name, strlen('api.modules.')))[0];

            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        }

        return $this->slugs = array_keys($slugs);
    }
}
