<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Controllers;

use App\Modules\Mcp\Http\Middleware\EnsureConnectorCapability;
use App\Modules\Mcp\OAuth\Clients\Clients;
use App\Modules\Mcp\OAuth\OAuthError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dynamic client registration, RFC 7591, for a client without a metadata document (design §3.2). */
final class RegisterController
{
    public function __invoke(Request $request, Clients $clients): JsonResponse
    {
        if (! EnsureConnectorCapability::granted()) {
            return (new OAuthError('invalid_request', 'This site does not offer an AI assistant connector.', 404))->toResponse();
        }

        /** @var array<string, mixed> $input */
        $input = $request->isJson() ? $request->json()->all() : $request->all();

        try {
            ['client' => $client, 'created' => $created] = $clients->register($input);
        } catch (OAuthError $e) {
            return $e->toResponse();
        }

        return response()->json(array_filter([
            'client_id' => $client->client_id,
            'client_id_issued_at' => $client->created_at->getTimestamp(),
            'client_name' => $client->name,
            'client_uri' => $client->client_uri,
            'logo_uri' => $client->logo_uri,
            'redirect_uris' => $client->redirect_uris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], fn (mixed $value): bool => $value !== null), $created ? 201 : 200)->header('Cache-Control', 'no-store');
    }
}
