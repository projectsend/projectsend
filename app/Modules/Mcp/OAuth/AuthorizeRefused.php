<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

use RuntimeException;

/**
 * An authorization request that cannot proceed. With a redirect, the
 * client is told at the address it registered (RFC 6749 §4.1.2.1);
 * without one, the person is told on screen, because nothing before the
 * client and its redirect validated may send anybody anywhere.
 */
final class AuthorizeRefused extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly ?string $redirectTo = null,
    ) {
        parent::__construct($error.': '.$description);
    }
}
