<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

use App\Modules\Mcp\Support\ConnectorPlan;
use App\Modules\Mcp\Support\Scope;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Who this authorization server says it is, and the two documents a
 * client discovers it from (RFC 9728 and RFC 8414).
 *
 * Always the platform address — `APP_URL`, never the host a request
 * happened to arrive on. A client that connected to one address keeps
 * its token for that address, and the directory listing names the
 * platform pattern; a customer's own domain, when those exist, answers
 * these documents pointing here (plan decision 15).
 */
final class Issuer
{
    public static function url(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** The canonical resource identifier, RFC 8707: no trailing slash. */
    public static function resource(): string
    {
        return self::url().'/mcp';
    }

    public static function resourceMetadataUrl(): string
    {
        return self::url().'/.well-known/oauth-protected-resource/mcp';
    }

    /**
     * `mcp:own` is announced only where a client account may connect
     * (ConnectorPlan). Everywhere else a client is refused at the
     * consent, and advertising the scope would invite a request that
     * cannot be granted.
     *
     * @return list<string>
     */
    public static function scopesSupported(): array
    {
        return ConnectorPlan::current()->clientsMayConnect()
            ? [Scope::OWN, Scope::READ, Scope::WRITE]
            : [Scope::READ, Scope::WRITE];
    }

    /**
     * @return array<string, mixed>
     */
    public static function protectedResourceMetadata(): array
    {
        $site = app(Settings::class)->get(Setting::SiteName);

        return [
            'resource' => self::resource(),
            'authorization_servers' => [self::url()],
            'scopes_supported' => self::scopesSupported(),
            'bearer_methods_supported' => ['header'],
            // Once, not "ProjectSend — ProjectSend", on a site never renamed.
            'resource_name' => is_string($site) && $site !== '' && strcasecmp(trim($site), 'ProjectSend') !== 0 ? 'ProjectSend — '.$site : 'ProjectSend',
            'resource_documentation' => (string) config('mcp-connector.docs_url'),
        ];
    }

    /**
     * `code_challenge_methods_supported` is not decorative: without it the
     * specification tells a client to abort.
     *
     * @return array<string, mixed>
     */
    public static function authorizationServerMetadata(): array
    {
        $base = self::url();

        return [
            'issuer' => $base,
            'authorization_endpoint' => $base.'/oauth/mcp/authorize',
            'token_endpoint' => $base.'/oauth/mcp/token',
            'registration_endpoint' => $base.'/oauth/mcp/register',
            'revocation_endpoint' => $base.'/oauth/mcp/revoke',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => self::scopesSupported(),
            'client_id_metadata_document_supported' => true,
        ];
    }
}
