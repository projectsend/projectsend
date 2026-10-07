<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Ldap\LdapDirectory;
use App\Modules\Identity\Ldap\LdapIdentity;
use App\Modules\Identity\Ldap\LdapProbeResult;
use RuntimeException;

/**
 * A directory that lives in an array.
 *
 * Swapped in with `$this->swap(LdapDirectory::class, ...)`, this exercises
 * everything above the wire — which account types may authenticate,
 * provisioning, the two-factor hand-off, rate limiting — with no server
 * anywhere. It also counts calls, so a test can assert the directory was
 * *not* consulted, which is how the "staff never reach LDAP" and "a valid
 * local password costs no directory traffic" properties are proven.
 */
class FakeLdapDirectory implements LdapDirectory
{
    public int $calls = 0;

    /** @var list<string> */
    public array $attemptedEmails = [];

    /** @var list<string> */
    public array $lookedUpUsernames = [];

    /** Makes entries() throw, as an unreachable directory would. */
    public bool $listingFails = false;

    /**
     * @param  array<string, array{password: string, name?: string, dn?: string, username?: string}>  $entries  keyed by email
     */
    public function __construct(private readonly array $entries = []) {}

    public function authenticate(string $email, string $password): ?LdapIdentity
    {
        $this->calls++;
        $this->attemptedEmails[] = $email;

        $entry = $this->entries[$email] ?? null;

        if ($entry === null || $entry['password'] !== $password) {
            return null;
        }

        return new LdapIdentity(
            dn: $entry['dn'] ?? "uid={$email},ou=people,dc=example,dc=test",
            email: $email,
            name: $entry['name'] ?? 'Directory Person',
        );
    }

    public function emailForUsername(string $username): ?string
    {
        $this->calls++;
        $this->lookedUpUsernames[] = $username;

        $matches = array_keys(array_filter(
            $this->entries,
            fn (array $entry): bool => ($entry['username'] ?? null) === $username,
        ));

        return count($matches) === 1 ? $matches[0] : null;
    }

    public function entries(): array
    {
        if ($this->listingFails) {
            throw new RuntimeException('Directory unreachable.');
        }

        $identities = [];

        foreach ($this->entries as $email => $entry) {
            $identities[] = new LdapIdentity(
                dn: $entry['dn'] ?? "uid={$email},ou=people,dc=example,dc=test",
                email: $email,
                name: $entry['name'] ?? 'Directory Person',
            );
        }

        return $identities;
    }

    public function probe(?string $email = null, ?string $password = null): LdapProbeResult
    {
        return LdapProbeResult::ok(LdapProbeResult::STAGE_SERVICE_BIND, 'Fake directory reachable.');
    }
}
