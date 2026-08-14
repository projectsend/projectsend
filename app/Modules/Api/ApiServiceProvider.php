<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Modules\Platform\Capabilities\Edition;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\ServiceProvider;

/**
 * Cross-cutting API concerns only. Domain endpoints live in their own
 * module under an Api sub-namespace — this provider knows nothing about
 * files, clients or groups.
 */
class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiModuleRegistry::class);
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->describeOpenApi();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\PurgeApiRequestLogsCommand::class,
            ]);
        }
    }

    /**
     * Two things Scramble cannot infer from the code, both of which a
     * consumer needs before it can make a single successful call.
     *
     * Scramble's own docs routes stay unmounted: the reference lives in the
     * admin UI behind the same gate as every other system settings page,
     * and a second, separately-gated copy of the same document is a
     * surface nobody would remember to check.
     */
    private function describeOpenApi(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::ignoreDefaultRoutes();

        // Replaces the config's `api_path` matcher so two routes can be
        // left out: the document must not describe how to fetch itself,
        // and module endpoints belong in their own package's document —
        // the committed core spec has to be identical on every install
        // regardless of which optional packages are present.
        Scramble::routes(fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'api/v1/')
            && $route->uri() !== 'api/v1/openapi.json'
            && ! str_starts_with($route->uri(), 'api/v1/modules/'));

        Scramble::extendOpenApi(function (OpenApi $document): void {
            // Every route in this document sits behind auth:sanctum, so the
            // scheme is declared once and applied globally.
            $document->secure(SecurityScheme::http('bearer'));
        });

        Scramble::afterOpenApiGenerated(function (OpenApi $document): void {
            // A *relative* server URL. Scramble defaults to an absolute one
            // built from APP_URL, which bakes whichever machine ran the
            // export into a document that then ships to every installation
            // — every importing client would have been pointed at the
            // exporter's host. Relative resolves against wherever the spec
            // was fetched from, which is always the right server.
            $document->servers = [Server::make('/api/v1')];

            // The token abilities each endpoint needs live in `token-can:`
            // route middleware, which no amount of return-type inference
            // will reveal. Without them a reader can see the shape of a
            // call but not which permissions to tick when creating the
            // token that makes it — the single most common reason a first
            // request 403s.
            $abilitiesByRoute = $this->abilitiesByRoute();

            foreach ($document->paths as $path) {
                foreach ($path->operations as $operation) {
                    $key = strtoupper($operation->method).' '.ltrim($path->path, '/');
                    $abilities = $abilitiesByRoute[$key] ?? null;

                    if ($abilities === null) {
                        continue;
                    }

                    $operation->description = trim(
                        $operation->description
                        ."\n\nRequires a token with "
                        .(count($abilities) > 1 ? 'any of these abilities' : 'the ability')
                        .': `'.implode('`, `', $abilities).'`.'
                    );
                }
            }
        });
    }

    /**
     * "METHOD uri" (uri relative to the api/v1 prefix) => the abilities its
     * `token-can:` middleware names.
     *
     * Keyed by method as well as path, because several endpoints share a
     * URI with different requirements — `GET /files` needs any of the three
     * view permissions while `POST /files` needs `upload`, and keying by
     * path alone silently gave every operation on a shared path whichever
     * route happened to be registered last.
     *
     * @return array<string, list<string>>
     */
    private function abilitiesByRoute(): array
    {
        $map = [];

        foreach (Router::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'token-can:')) {
                    continue;
                }

                $abilities = array_values(array_filter(
                    array_map('trim', explode(',', substr($middleware, strlen('token-can:'))))
                ));

                foreach ($route->methods() as $method) {
                    if ($method === 'HEAD') {
                        continue;
                    }

                    $map[$method.' '.substr($route->uri(), strlen('api/v1/'))] = $abilities;
                }
            }
        }

        return $map;
    }

    private function registerRateLimiters(): void
    {
        // Keyed by token, not by user: two integrations belonging to the
        // same admin get independent allowances, so a runaway Zapier zap
        // cannot throttle that person's phone. IP is only the fallback for
        // requests that never authenticated.
        RateLimiter::for('api', fn (Request $request): Limit => $this->limit($request, 'default'));

        RateLimiter::for('api-uploads', fn (Request $request): Limit => $this->limit($request, 'uploads'));
    }

    private function limit(Request $request, string $bucket): Limit
    {
        $edition = config('projectsend.edition');
        $key = $edition instanceof Edition ? $edition->value : Edition::Community->value;

        $perMinute = (int) config(
            "api.rate_limits.{$key}.{$bucket}",
            config("api.rate_limits.community.{$bucket}", 60)
        );

        $token = $request->user()?->currentAccessToken();

        return Limit::perMinute($perMinute)->by(
            $token !== null ? "token:{$token->getKey()}:{$bucket}" : "ip:{$request->ip()}:{$bucket}"
        );
    }
}
