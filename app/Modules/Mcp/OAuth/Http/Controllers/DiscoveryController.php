<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Controllers;

use App\Modules\Mcp\OAuth\Issuer;
use Illuminate\Http\JsonResponse;

/**
 * The two documents a client discovers this server from (design §6):
 * what the resource is and who issues tokens for it, and what that
 * issuer supports.
 */
final class DiscoveryController
{
    public function protectedResource(): JsonResponse
    {
        return $this->document(Issuer::protectedResourceMetadata());
    }

    public function authorizationServer(): JsonResponse
    {
        return $this->document(Issuer::authorizationServerMetadata());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function document(array $body): JsonResponse
    {
        return response()->json($body)->header('Cache-Control', 'public, max-age=300');
    }
}
