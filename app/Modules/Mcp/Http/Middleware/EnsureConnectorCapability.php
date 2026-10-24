<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Middleware;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate, answered in the shape an MCP client reads: the capability
 * (the edition's and the platform's say) and the installation's own
 * switch, Setting::AiConnectorEnabled.
 *
 * The app's own `capability:` middleware is right for its screens and
 * wrong here: on a JSON request it throws expecting the API's problem
 * renderer, which only handles `/api/*` — at `/mcp` the exception would
 * escape as a bare 500, and a client would retry a server that is never
 * going to answer. So the question is asked here and refused as a 404:
 * the same answer the app gives for a feature this installation does
 * not have.
 */
class EnsureConnectorCapability
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::granted()) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32004, 'message' => 'This site does not offer an AI assistant connector.'],
                'id' => null,
            ], 404);
        }

        return $next($request);
    }

    public static function granted(): bool
    {
        return app(CapabilityRegistry::class)->has(Capability::AiConnector)
            && app(Settings::class)->get(Setting::AiConnectorEnabled) === true;
    }
}
