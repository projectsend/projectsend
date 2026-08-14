<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

/**
 * How the connection to the directory is protected.
 *
 * One field with three values rather than a pair of booleans, and
 * deliberately no "don't verify the certificate" escape hatch — that
 * setting is the most-abused option in every LDAP integration and turns
 * "encrypted" into "encrypted to whoever answered". A corporate
 * self-signed CA is the real need behind it, and LdapSettings answers that
 * with a CA certificate path instead.
 */
enum LdapEncryption: string
{
    case None = 'none';
    case Ssl = 'ssl';
    case Tls = 'tls';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None (unencrypted)',
            self::Ssl => 'LDAPS (implicit TLS)',
            self::Tls => 'StartTLS',
        };
    }

    /** The port this scheme conventionally listens on. */
    public function defaultPort(): int
    {
        return match ($this) {
            self::Ssl => 636,
            default => 389,
        };
    }
}
