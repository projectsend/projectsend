<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use RuntimeException;

/**
 * A failed token exchange or refresh.
 *
 * `needsReconnect` separates the two situations an admin can be in: the
 * grant itself is dead (revoked consent, password/Conditional-Access
 * change, expired refresh token — only re-running the connect flow
 * helps) versus a transient failure (endpoint unreachable, 5xx) where
 * the existing connection is fine and retrying is the answer.
 */
class MailOAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $needsReconnect = false,
    ) {
        parent::__construct($message);
    }
}
