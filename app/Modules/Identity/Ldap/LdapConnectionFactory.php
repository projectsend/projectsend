<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

use LdapRecord\Connection;

/**
 * Builds the LdapRecord connection from the stored settings.
 *
 * `configFor()` is a pure function on purpose. v1's worst LDAP bug was a
 * `use_tls` setting with no `ldap_start_tls()` call anywhere behind it —
 * every installation that believed it was encrypted was shipping its bind
 * password in clear, and nothing could have caught that except reading the
 * code. Keeping the mapping as a function of its input means a test can
 * assert "encryption = tls produces use_tls" directly, forever.
 */
class LdapConnectionFactory
{
    /**
     * @return array<string, mixed>
     */
    public static function configFor(LdapSettings $settings): array
    {
        // Built statement by statement rather than as one literal. The
        // options are keyed by PHP's LDAP_OPT_* integer constants, and both
        // clever ways of assembling them quietly lose entries: array_filter()
        // with no callback drops LDAP_OPT_REFERRALS because its value is 0,
        // and the spread operator renumbers integer keys outright. Either
        // one leaves referrals enabled, which is the Active Directory
        // misbehaviour the option exists to prevent.
        $options = [
            LDAP_OPT_PROTOCOL_VERSION => 3,
            LDAP_OPT_NETWORK_TIMEOUT => 5,
            LDAP_OPT_REFERRALS => 0,
        ];

        if ($settings->ca_cert_path !== null && $settings->ca_cert_path !== '') {
            $options[LDAP_OPT_X_TLS_CACERTFILE] = $settings->ca_cert_path;
        }

        return [
            'hosts' => [$settings->host],
            'port' => $settings->port,
            'base_dn' => $settings->base_dn,
            // Empty means an anonymous bind, which some directories allow
            // for the search step.
            'username' => $settings->bind_dn !== '' ? $settings->bind_dn : null,
            'password' => $settings->bind_password !== '' ? $settings->bind_password : null,
            'use_ssl' => $settings->encryption === LdapEncryption::Ssl,
            'use_tls' => $settings->encryption === LdapEncryption::Tls,
            // A directory that stops answering must not hold a PHP-FPM
            // worker for LDAP's 30-second default; see LdapAuthenticator's
            // circuit breaker for the other half of that.
            'timeout' => 5,
            'options' => $options,
        ];
    }

    public static function make(LdapSettings $settings): Connection
    {
        // LDAP_OPT_X_TLS_REQUIRE_CERT is deliberately never set, leaving the
        // system default of `demand`. There is no code path here that
        // weakens certificate verification.
        return new Connection(self::configFor($settings));
    }
}
