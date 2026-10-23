<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Middleware;

use App\Modules\Mcp\OAuth\Issuer;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The 401 an MCP client can act on.
 *
 * Without a token, or with one that is expired, revoked or unknown, the
 * guard behind this throws. Left to the app, that exception becomes a
 * redirect to the login page for a browser and a bare JSON 401 for
 * anything else — and an MCP client needs neither. What it needs is
 * the header RFC 9728 describes: where the protected-resource metadata
 * is, and which scopes to ask for, so it can discover the authorization
 * server and start the flow on its own. The specification makes that
 * header one of the two ways a client may discover the server, and the
 * one it tries first.
 *
 * Sits *before* `auth:sanctum` in the route's middleware so it sees
 * what the guard refused. The body is JSON-RPC shaped so a client that
 * reads bodies sees an error object rather than an HTML page, and the
 * status is what the specification requires: 401, never 403, for a
 * missing or invalid token.
 */
class RequireBearerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (AuthenticationException) {
            return $this->unauthorized($request);
        }

        // The router's pipeline renders an exception thrown by an inner
        // middleware into a response before it reaches this one, so the
        // guard's refusal arrives here as the framework's plain 401. It
        // is replaced, not decorated: the body and the header are what a
        // client acts on.
        if ($response->getStatusCode() === 401) {
            return $this->unauthorized($request);
        }

        return $response;
    }

    private function unauthorized(Request $request): Response
    {
        // The platform address, never the host the request arrived on:
        // the issuer a client discovers has to be the one it will hold
        // its token for (design §6.4).
        $metadata = Issuer::resourceMetadataUrl();
        $scopes = Issuer::scopesSupported();

        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => 'A bearer token is required. Connect this assistant to the account first.',
            ],
            'id' => null,
        ], 401)->header(
            'WWW-Authenticate',
            sprintf('Bearer realm="mcp", resource_metadata="%s", scope="%s"', $metadata, implode(' ', $scopes)),
        );
    }
}
