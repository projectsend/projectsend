<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Controllers;

use App\Modules\Mcp\Http\Middleware\EnsureConnectorCapability;
use App\Modules\Mcp\OAuth\Grants;
use App\Modules\Mcp\OAuth\OAuthError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `POST /oauth/mcp/token`: a code for a token, or a refresh for the next one (design §5). */
final class TokenController
{
    public function __invoke(Request $request, Grants $grants): JsonResponse
    {
        try {
            if (! EnsureConnectorCapability::granted()) {
                throw new OAuthError('invalid_request', 'This site does not offer an AI assistant connector.', 404);
            }

            $response = match ((string) $request->input('grant_type', '')) {
                'authorization_code' => $grants->exchangeCode(
                    (string) $request->input('code', ''),
                    (string) $request->input('client_id', ''),
                    (string) $request->input('redirect_uri', ''),
                    (string) $request->input('code_verifier', ''),
                    $request->filled('resource') ? (string) $request->input('resource') : null,
                ),
                'refresh_token' => $grants->refresh(
                    (string) $request->input('refresh_token', ''),
                    (string) $request->input('client_id', ''),
                    $request->filled('scope') ? (string) $request->input('scope') : null,
                ),
                default => throw new OAuthError('unsupported_grant_type', 'grant_type must be authorization_code or refresh_token.'),
            };
        } catch (OAuthError $e) {
            return $e->toResponse();
        }

        return response()->json($response)->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }
}
