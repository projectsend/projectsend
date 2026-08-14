<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

/**
 * A directory entry whose password has just been verified.
 *
 * Only the three things this app acts on. Everything else a directory
 * knows about a person stays in the directory.
 */
final readonly class LdapIdentity
{
    public function __construct(
        public string $dn,
        public string $email,
        public string $name,
    ) {}
}
