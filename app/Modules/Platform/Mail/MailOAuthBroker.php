<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

/**
 * One OAuth mail provider's token machinery: building the consent URL,
 * turning the returned code into tokens, and keeping those tokens fresh.
 *
 * Deliberately not Socialite: a mail connection needs raw tokens with a
 * send scope, not a user identity, and Socialite's user() call would
 * drag in a userinfo permission (User.Read on Graph) that sending mail
 * does not need. Implementations write their results straight onto the
 * MailOAuthConnection row and save it.
 */
interface MailOAuthBroker
{
    /** The provider consent URL the admin's browser is sent to. */
    public function authorizeUrl(MailOAuthConnection $connection, string $state, string $redirectUri): string;

    /**
     * Exchange the callback's authorization code for tokens and record
     * them, along with the connected mailbox's address, on the connection.
     *
     * @throws MailOAuthException
     */
    public function exchange(MailOAuthConnection $connection, string $code, string $redirectUri): void;

    /**
     * Refresh the access token (rotating the refresh token when the
     * provider hands back a new one) and record the outcome — including
     * `last_error` on failure, so the settings page and the scheduled
     * health check read one source of truth.
     *
     * @throws MailOAuthException
     */
    public function refresh(MailOAuthConnection $connection): void;

    /**
     * A refresh that is not racing a send: the scheduled health check's
     * way in, serialised against freshAccessToken() on the same
     * connection.
     *
     * @throws MailOAuthException
     */
    public function refreshSerially(MailOAuthConnection $connection): void;

    /**
     * An access token currently valid for at least a small safety margin,
     * refreshing first when needed — what transports call at send time.
     *
     * @throws MailOAuthException
     */
    public function freshAccessToken(MailOAuthConnection $connection): string;
}
