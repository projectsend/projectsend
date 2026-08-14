<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

/**
 * What the settings screen's test button reports.
 *
 * `stage` is the point the attempt got to, which is the genuinely useful
 * part: "connect" and "service_bind" failing mean very different things to
 * whoever is filling the form in.
 */
final readonly class LdapProbeResult
{
    public const STAGE_CONNECT = 'connect';

    public const STAGE_SERVICE_BIND = 'service_bind';

    public const STAGE_SEARCH = 'search';

    public const STAGE_USER_BIND = 'user_bind';

    public function __construct(
        public bool $ok,
        public string $stage,
        public string $message,
        public ?string $dn = null,
    ) {}

    public static function ok(string $stage, string $message, ?string $dn = null): self
    {
        return new self(true, $stage, $message, $dn);
    }

    public static function failed(string $stage, string $message): self
    {
        return new self(false, $stage, $message);
    }
}
