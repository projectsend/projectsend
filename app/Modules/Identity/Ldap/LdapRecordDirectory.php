<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

use Illuminate\Support\Facades\Log;
use LdapRecord\Connection;
use Throwable;

/**
 * The production directory adapter.
 *
 * Two binds against one connection: bind as the service account and search
 * for the entry, then re-bind as the DN that search returned, using the
 * supplied password.
 *
 * That order is the security design, not an implementation detail. v1 built
 * its filter by interpolating the submitted email — `"(mail=$email)"` — so a
 * crafted address rewrote the query. Searching through the query builder
 * escapes the value, but the load-bearing part is that the DN we bind as is
 * always one the directory just handed back: even a filter that somehow
 * matched more than intended cannot produce a bind as an attacker-chosen
 * entry, and more than one match fails closed rather than picking the first.
 *
 * Nothing is logged on success. Failures log a fixed message and the
 * exception class — never the email, the DN, the filter or the password.
 * v1 left ~15 unconditional debug lines that recorded the bind DN and every
 * address anyone typed into the login form.
 */
class LdapRecordDirectory implements LdapDirectory
{
    public function authenticate(string $email, string $password): ?LdapIdentity
    {
        $settings = LdapSettings::current();

        if (! $settings->usable()) {
            return null;
        }

        try {
            $connection = LdapConnectionFactory::make($settings);
            $connection->connect();

            $entry = $this->findEntry($connection, $settings, $email);

            if ($entry === null) {
                return null;
            }

            $dn = (string) ($entry['dn'] ?? '');

            if ($dn === '' || ! $connection->auth()->attempt($dn, $password)) {
                return null;
            }

            return new LdapIdentity(
                dn: $dn,
                email: $this->attribute($entry, $settings->email_attribute) ?? $email,
                name: $this->attribute($entry, $settings->name_attribute) ?? $email,
            );
        } catch (Throwable $e) {
            Log::warning('LDAP authentication could not be completed.', ['exception' => $e::class]);

            return null;
        }
    }

    public function probe(?string $email = null, ?string $password = null): LdapProbeResult
    {
        $settings = LdapSettings::current();

        if (! extension_loaded('ldap')) {
            return LdapProbeResult::failed(
                LdapProbeResult::STAGE_CONNECT,
                __('The PHP LDAP extension is not installed on this server.'),
            );
        }

        try {
            $connection = LdapConnectionFactory::make($settings);
            $connection->connect();
        } catch (Throwable $e) {
            return LdapProbeResult::failed(
                LdapProbeResult::STAGE_SERVICE_BIND,
                __('Could not connect or bind as the service account: :error', ['error' => $e->getMessage()]),
            );
        }

        if ($email === null || $email === '') {
            return LdapProbeResult::ok(
                LdapProbeResult::STAGE_SERVICE_BIND,
                __('Connected and bound as the service account.'),
            );
        }

        try {
            $entry = $this->findEntry($connection, $settings, $email);
        } catch (Throwable $e) {
            return LdapProbeResult::failed(
                LdapProbeResult::STAGE_SEARCH,
                __('The search failed: :error', ['error' => $e->getMessage()]),
            );
        }

        if ($entry === null) {
            return LdapProbeResult::failed(
                LdapProbeResult::STAGE_SEARCH,
                __('No directory entry matched that address under the base DN.'),
            );
        }

        $dn = (string) ($entry['dn'] ?? '');

        if ($password === null || $password === '') {
            return LdapProbeResult::ok(LdapProbeResult::STAGE_SEARCH, __('Found a matching entry.'), $dn);
        }

        try {
            $bound = $connection->auth()->attempt($dn, $password);
        } catch (Throwable $e) {
            return LdapProbeResult::failed(
                LdapProbeResult::STAGE_USER_BIND,
                __('The sign-in bind failed: :error', ['error' => $e->getMessage()]),
            );
        }

        return $bound
            ? LdapProbeResult::ok(LdapProbeResult::STAGE_USER_BIND, __('Signed in successfully.'), $dn)
            : LdapProbeResult::failed(LdapProbeResult::STAGE_USER_BIND, __('The directory rejected that password.'));
    }

    /**
     * The one entry matching this address, or null.
     *
     * Two results is a misconfiguration — two objects sharing an address —
     * and choosing one of them is how you sign the wrong person in, so it
     * fails closed.
     *
     * @return array<string, mixed>|null
     */
    private function findEntry(Connection $connection, LdapSettings $settings, string $email): ?array
    {
        $query = $connection->query()
            ->in($settings->base_dn)
            // The email goes through the builder, which escapes it. It is
            // never concatenated into a filter string.
            ->whereEquals($settings->email_attribute, $email);

        if (is_string($settings->user_filter) && $settings->user_filter !== '') {
            // Admin-supplied, never visitor-supplied.
            $query->rawFilter($settings->user_filter);
        }

        $results = $query->limit(2)->get();

        return count($results) === 1 ? $results[0] : null;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function attribute(array $entry, string $name): ?string
    {
        $value = $entry[strtolower($name)] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
