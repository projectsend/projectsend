<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Controllers;

use App\Modules\Mcp\OAuth\Grants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `POST /oauth/mcp/revoke`, RFC 7009: always 200, whatever the token named (design §5.3). */
final class RevokeController
{
    public function __invoke(Request $request, Grants $grants): JsonResponse
    {
        $hint = $request->input('token_type_hint');

        $grants->revokeByToken((string) $request->input('token', ''), is_string($hint) ? $hint : null);

        return response()->json((object) [])->header('Cache-Control', 'no-store');
    }
}
