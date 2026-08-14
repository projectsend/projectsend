<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every API request.
 *
 * Applied to the whole `api` middleware group rather than per route, so an
 * endpoint added later is measured without anyone remembering to opt in —
 * the same reasoning as stamping origin inside ActivityLogger.
 *
 * The write happens in terminate(), not handle(), for two reasons. A
 * failed request never returns through handle() at all: `auth:sanctum`
 * throws, and the exception travels past this middleware to be turned into
 * a 401 by the handler, so recording after `$next` would have logged only
 * the successes — the flattering half, and useless in an incident.
 * terminate() also runs after the response has been sent, so telemetry
 * costs the caller nothing.
 */
class RecordApiRequest
{
    private const STARTED_AT = 'api_request_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        // On the request rather than on `$this`: terminable middleware is
        // re-resolved from the container, so instance state is not
        // guaranteed to survive to terminate().
        $request->attributes->set(self::STARTED_AT, microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $startedAt = $request->attributes->get(self::STARTED_AT);
            $token = $request->user()?->currentAccessToken();

            ApiRequestLog::query()->create([
                'api_token_id' => $token?->getKey(),
                // Snapshotted so a revoked token's history is still readable
                // — which is precisely when someone reviews it.
                'api_token_name' => $token?->getAttribute('name'),
                'user_id' => $request->user()?->getKey(),
                'method' => $request->getMethod(),
                'route' => $this->routePattern($request),
                'status' => $response->getStatusCode(),
                'duration_ms' => is_float($startedAt) ? (int) round((microtime(true) - $startedAt) * 1000) : 0,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Telemetry must never turn a successful API call into a failed
            // one. A full disk or a locked table is an operations problem,
            // not the caller's — and by this point the response has already
            // gone out regardless.
            Log::warning('Failed to record an API request: '.$e->getMessage());
        }
    }

    /**
     * The matched route's *pattern*, never the resolved URI: a URI carries
     * the ids of the clients and files a caller touched, and that belongs
     * in the audit log rather than in volume telemetry. A request that
     * matched nothing is recorded under a placeholder, so 404 noise stays
     * countable without recording what was probed.
     */
    private function routePattern(Request $request): string
    {
        $route = $request->route();

        return $route === null ? '(unmatched)' : $route->uri();
    }
}
