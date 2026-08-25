<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

/**
 * Google OAuth tokens for sending through the Gmail API.
 *
 * Same delegated shape as Microsoft's: an administrator signs into the
 * Google account the installation should send as, and the token can
 * send as that account and nothing else. The app registration lives in
 * Google Cloud Console (an OAuth client of type "Web application");
 * a Workspace admin can mark it Internal, everyone else runs it in
 * testing/published status — in testing, Google expires the refresh
 * token after 7 days, which the daily health check surfaces as a
 * reconnect warning rather than silent dead mail.
 */
class GoogleMailBroker extends OAuthCodeFlowBroker
{
    /**
     * gmail.send is the one permission sending needs; openid/email buy
     * the id_token the connected account's address is read from — no
     * userinfo call, no broader Gmail access.
     */
    private const SCOPE = 'openid email https://www.googleapis.com/auth/gmail.send';

    public function authorizeUrl(MailOAuthConnection $connection, string $state, string $redirectUri): string
    {
        // access_type=offline is what makes Google issue a refresh token
        // at all, and prompt must include 'consent' because Google only
        // hands one out while showing the consent screen — a silent
        // re-auth returns none, and this flow cannot run on borrowed
        // time. select_account for the same reason as Microsoft's: the
        // sending account is usually not the one the admin is signed
        // into.
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => (string) $connection->client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => self::SCOPE,
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account consent',
        ]);
    }

    protected function tokenEndpoint(MailOAuthConnection $connection): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function scope(): string
    {
        return self::SCOPE;
    }
}
