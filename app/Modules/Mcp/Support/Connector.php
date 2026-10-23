<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use Illuminate\Support\Str;

/**
 * Who is calling: the assistant behind the token, as a name for the
 * activity log and a slug for the sign-up links it may hand out.
 *
 * Every credential at /mcp is a Sanctum token, and a Sanctum token has a
 * name. An OAuth grant is named `AI assistant · Claude` when the consent
 * mints it; an API token is named by whoever minted it on the web. Both
 * are what the person sees beside the entry in their activity, and both
 * are worth carrying as-is: revoking the credential must not leave the
 * entry pointing at nothing, which is why the app snapshots the name
 * rather than the id.
 */
final class Connector
{
    /** How the consent screen names the tokens it mints, before the client's name. */
    public const TOKEN_PREFIX = 'AI assistant · ';

    private function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly bool $isAssistantGrant,
    ) {}

    public static function fromTokenName(?string $tokenName): self
    {
        $raw = trim((string) $tokenName);

        if ($raw === '') {
            return new self('AI assistant', 'assistant', false);
        }

        $isGrant = str_starts_with($raw, self::TOKEN_PREFIX);
        $name = $isGrant ? trim(substr($raw, strlen(self::TOKEN_PREFIX))) : $raw;

        if ($name === '') {
            $name = 'AI assistant';
        }

        return new self($name, self::slug($name), $isGrant);
    }

    /**
     * Lowercase, hyphens, nothing else: `mcp_client=claude`,
     * `mcp_client=claude-code`, `mcp_client=my-n8n-token`. The portal
     * keeps it verbatim as attribution, so it has to be safe in a query
     * string and readable in a table.
     */
    public static function slug(string $name): string
    {
        $slug = Str::slug($name, '-');

        return $slug === '' ? 'assistant' : Str::limit($slug, 60, '');
    }
}
