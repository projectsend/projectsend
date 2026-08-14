<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use App\Modules\Platform\Capabilities\CapabilityUnavailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * RFC 7807 error bodies for /api/* only.
 *
 * Two properties this class exists to guarantee:
 *
 *  - Every API failure has the same shape, so a client can parse errors
 *    once. `type` is a stable slug a caller may branch on; `title` and
 *    `detail` are prose that may be reworded without it being a breaking
 *    change.
 *  - Nothing leaks. Laravel's default JSON renderer happily returns an
 *    exception message and stack trace; here the message is only ever
 *    echoed for exceptions that are meant to be read by the caller
 *    (validation, explicit HTTP aborts), and anything else collapses to a
 *    generic 500 unless APP_DEBUG is on.
 */
class ProblemDetails
{
    /**
     * Slugs are part of the API contract — rename one and you have made a
     * breaking change. Keyed by status code for everything that doesn't
     * carry a more specific slug of its own.
     *
     * @var array<int, string>
     */
    private const TYPES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        500 => 'server_error',
        503 => 'service_unavailable',
    ];

    public function shouldHandle(Request $request): bool
    {
        return $request->is('api/*');
    }

    public function render(Request $request, Throwable $e): JsonResponse
    {
        [$status, $type, $title, $detail, $extra] = $this->describe($e);

        $body = array_filter([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], static fn (mixed $value): bool => $value !== null) + $extra;

        $response = new JsonResponse($body, $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        // Throttling and 405s carry headers the caller genuinely needs
        // (Retry-After, Allow); dropping them would make the error
        // unactionable, so copy whatever the exception already decided.
        if ($e instanceof HttpExceptionInterface) {
            foreach ($e->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * @return array{int, string, string, string|null, array<string, mixed>}
     */
    private function describe(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return [
                422,
                'validation_failed',
                'The given data was invalid.',
                null,
                ['errors' => $e->errors()],
            ];
        }

        if ($e instanceof AuthenticationException) {
            return [
                401,
                'unauthenticated',
                'Authentication required.',
                'Send a valid API token in the Authorization header as "Bearer <token>".',
                [],
            ];
        }

        if ($e instanceof AuthorizationException) {
            return [
                403,
                'forbidden',
                'This action is unauthorized.',
                // An authorization failure must not explain itself in
                // detail: "you lack delete_others_files" and "that file
                // belongs to someone else" are both facts about data the
                // caller was just told it cannot see.
                null,
                [],
            ];
        }

        // Before the generic HttpExceptionInterface branch, which would
        // flatten this to a bare `forbidden` and drop the two fields that
        // tell a caller *why* — an integration wants to distinguish "your
        // token may not do that" from "this installation does not have
        // that feature", and only the second is worth giving up on.
        if ($e instanceof CapabilityUnavailable) {
            return [
                403,
                'capability_unavailable',
                'Not available in this edition.',
                $e->getMessage(),
                ['capability' => $e->capability->value, 'edition' => $e->edition->value],
            ];
        }

        // Note what this does NOT do: a file that exists but is out of the
        // caller's scope answers 403, while an unknown id answers 404, so
        // the pair does reveal whether a given id exists. That is the same
        // answer the web UI gives (routes/web.php binds the model, then the
        // policy refuses), and mirroring it is deliberate — the API adds no
        // exposure a staff member does not already have by visiting
        // /files/{id}. Collapsing both to 404 would be a stricter boundary
        // than the app applies anywhere else, and only in one surface.
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return [404, 'not_found', 'Resource not found.', null, []];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();

            return [
                $status,
                self::TYPES[$status] ?? 'http_error',
                $this->titleFor($status),
                $message !== '' ? $message : null,
                [],
            ];
        }

        return [
            500,
            'server_error',
            'Server error.',
            // The only place a raw exception message may surface, and only
            // when the operator has explicitly asked for it.
            config('app.debug') === true ? $e->getMessage() : null,
            [],
        ];
    }

    private function titleFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'This action is unauthorized.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            409 => 'Conflict.',
            413 => 'Payload too large.',
            429 => 'Too many requests.',
            503 => 'Service unavailable.',
            default => 'Request failed.',
        };
    }
}
