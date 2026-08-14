<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OpenAPI document, served from the copy committed to the repository
 * rather than generated per request.
 *
 * Generating on request would make the document a property of whichever
 * packages happen to be installed on that particular server, and would put
 * route reflection on the hot path of a public, unauthenticated endpoint.
 * The committed file is the contract; `php artisan scramble:export`
 * regenerates it and a test fails if the two drift.
 *
 * Unauthenticated on purpose: a client needs the spec *before* it has a
 * token, and the document is identical on every install — it describes the
 * shape of the API, never any of this installation's data. A test asserts
 * that second part, since it is the whole reason this is safe to leave open.
 */
class OpenApiController extends Controller
{
    public const PATH = 'docs/api/openapi.json';

    public function __invoke(): Response
    {
        $path = base_path(self::PATH);

        if (! is_file($path)) {
            // A deployment that skipped the export step. Better a clear 404
            // than an empty document a client would cache as the truth.
            return new JsonResponse([
                'type' => 'not_found',
                'title' => 'The OpenAPI document has not been generated for this installation.',
                'status' => 404,
            ], 404, ['Content-Type' => 'application/problem+json']);
        }

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
