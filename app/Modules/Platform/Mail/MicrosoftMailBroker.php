<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

/**
 * Microsoft identity platform (v2.0) tokens for sending through Graph.
 *
 * Delegated flow on purpose: an administrator signs into the mailbox the
 * installation should send as, and the resulting token can send as that
 * mailbox and nothing else — no admin consent, no PowerShell
 * ApplicationAccessPolicy, and it works for work/school and personal
 * accounts alike. The price of delegated is that the grant can die
 * behind our back (password reset, Conditional Access change), which is
 * why refresh failures record `last_error` for the health check to
 * surface instead of letting mail stop silently.
 */
class MicrosoftMailBroker extends OAuthCodeFlowBroker
{
    /**
     * Mail.Send is the one Graph permission sending needs; offline_access
     * buys the refresh token; openid/profile/email buy the id_token the
     * connected mailbox's address is read from — which is what lets the
     * whole flow avoid User.Read and a Graph /me call entirely.
     */
    private const SCOPE = 'offline_access openid profile email https://graph.microsoft.com/Mail.Send';

    public function authorizeUrl(MailOAuthConnection $connection, string $state, string $redirectUri): string
    {
        // select_account, always: the admin doing this is often signed
        // into their own mailbox, and the one the installation should
        // send as (noreply@, portal@) is usually a different one.
        return 'https://login.microsoftonline.com/'.$this->tenant($connection).'/oauth2/v2.0/authorize?'.http_build_query([
            'client_id' => (string) $connection->client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => self::SCOPE,
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    protected function tokenEndpoint(MailOAuthConnection $connection): string
    {
        return 'https://login.microsoftonline.com/'.$this->tenant($connection).'/oauth2/v2.0/token';
    }

    protected function scope(): string
    {
        return self::SCOPE;
    }

    /**
     * Blank falls back to 'common', which admits work/school accounts of
     * any tenant plus personal accounts — the inclusive default for this
     * app's audience. 'consumers' and 'organizations' work here too; a
     * Consumer-audience app registration in fact requires 'consumers',
     * as /common refuses that userAudience outright.
     */
    private function tenant(MailOAuthConnection $connection): string
    {
        $tenant = $connection->tenant_id;

        return is_string($tenant) && trim($tenant) !== '' ? trim($tenant) : 'common';
    }
}
