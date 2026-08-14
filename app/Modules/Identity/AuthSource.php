<?php

declare(strict_types=1);

namespace App\Modules\Identity;

/**
 * Where an account's credentials actually live.
 *
 * A string rather than an `is_ldap` boolean: a third source (SAML/OIDC) is
 * a stated goal for this product, not a hypothetical, and a boolean would
 * have to be replaced rather than extended.
 *
 * This is provenance and display, not authorisation. The login path
 * decides whether to consult a directory from the account's *type* — LDAP
 * is client-only — so a wrong value here cannot let a staff account
 * authenticate against a directory.
 */
enum AuthSource: string
{
    /** A password stored in this installation. */
    case Local = 'local';

    /** Created by, and verified against, an LDAP directory. */
    case Ldap = 'ldap';

    /**
     * Created by an OAuth2 / OpenID Connect provider on first sign-in.
     *
     * Unlike Ldap this does not mean "the password lives elsewhere and
     * the local hash is never consulted" — a social account may later set
     * a real password, and a *local* account may link a provider without
     * ever becoming this. It means the account came into existence
     * without anybody choosing a password for it, which is what
     * AccountConversion::requiresNewPassword() acts on.
     */
    case Social = 'social';
}
