<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

/**
 * Proof Key for Code Exchange, RFC 7636, S256 only. `plain` is refused:
 * it proves nothing to anyone who saw the code.
 */
final class Pkce
{
    private const SHAPE = '/^[A-Za-z0-9\-._~]{43,128}$/';

    public static function isWellFormed(string $value): bool
    {
        return preg_match(self::SHAPE, $value) === 1;
    }

    public static function challengeOf(string $verifier): string
    {
        return self::base64url(hash('sha256', $verifier, true));
    }

    public static function verifies(string $verifier, string $challenge): bool
    {
        return self::isWellFormed($verifier) && hash_equals($challenge, self::challengeOf($verifier));
    }

    public static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** A code or a refresh token: 32 random bytes, URL-safe. */
    public static function secret(): string
    {
        return self::base64url(random_bytes(32));
    }
}
