<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * The shape of every successful answer, in one place (spec §2.1).
 *
 * Every success carries three things beside the tool's own data: where
 * to see it on the web, what the assistant does next, and what the
 * person should know that did not stop the action. They are the same
 * three fields on every tool so the assistant learns the shape
 * once — and so a test can walk `tools/list` and check them all.
 *
 * Returned as structured content *and* as text: clients that read
 * structured content get an object, clients that do not see the same
 * JSON as text, and the model reads either.
 */
final class Envelope
{
    /**
     * @param  array<string, mixed>  $data  the tool's own fields
     * @param  list<string>  $warnings
     * @param  array<string, mixed>|null  $storage  the usage block, when the action changed it
     */
    public static function success(
        array $data,
        string $webUrl,
        string $nextSteps,
        array $warnings = [],
        ?array $storage = null,
    ): ResponseFactory {
        $payload = $data;

        if ($storage !== null) {
            $payload['storage'] = $storage;
        }

        $payload['web_url'] = $webUrl;
        $payload['next_steps'] = $nextSteps;
        $payload['warnings'] = $warnings;

        return Response::structured($payload);
    }
}
