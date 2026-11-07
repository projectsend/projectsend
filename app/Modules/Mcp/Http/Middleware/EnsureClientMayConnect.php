<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Middleware;

use App\Models\User;
use App\Modules\Mcp\Support\ConnectorPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A client account reaches /mcp only where clients may connect.
 *
 * The consent screen asks this before it grants a client anything. But a
 * grant outlives the answer: a client token minted while the hosted plan
 * let clients connect went on working after the plan stopped letting
 * them, because nothing at /mcp asked again. Asked here on every request,
 * the way EnsureConnectorCapability asks whether the connector is on.
 */
class EnsureClientMayConnect
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::allows($request->user())) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32003,
                    'message' => 'Client accounts cannot connect an assistant on this site.',
                ],
                'id' => null,
            ], 403);
        }

        return $next($request);
    }

    public static function allows(?User $user): bool
    {
        return $user === null || ! $user->isClient() || ConnectorPlan::current()->clientsMayConnect();
    }
}
