<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An OAuth error, the way RFC 6749 §5.2 spells it: a code a client
 * program reads and a description a person debugging it reads.
 */
final class OAuthError extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status = 400,
    ) {
        parent::__construct($error.': '.$description);
    }

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'error' => $this->error,
            'error_description' => $this->description,
        ], $this->status)->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }
}
