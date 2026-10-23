<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

use App\Modules\Mcp\Support\Scope;

/**
 * What a grant may be (design §7). A scope only subtracts: the ceiling
 * is what the person may do on the web today, asked per tool.
 */
final class Scopes
{
    public const ACCESS_READ = 'read';

    public const ACCESS_OPERATE = 'operate';

    /** @var list<string> */
    private const KNOWN = [Scope::OWN, Scope::READ, Scope::WRITE, Scope::USE];

    /**
     * The scopes a request asked for. Unknown ones are an error the
     * client is told about; `mcp:use` is accepted as "whatever you grant".
     *
     * @return list<string>
     *
     * @throws OAuthError
     */
    public static function parse(?string $scope): array
    {
        $requested = array_values(array_filter(explode(' ', trim((string) $scope)), fn (string $s): bool => $s !== ''));

        foreach ($requested as $one) {
            if (! in_array($one, self::KNOWN, true)) {
                throw new OAuthError('invalid_scope', "Unknown scope \"{$one}\". This server knows ".implode(', ', self::KNOWN).'.');
            }
        }

        return array_values(array_unique($requested));
    }

    /**
     * What the consent proposes to a staff member from what was asked:
     * read when only reading was asked for, operating otherwise.
     *
     * @param  list<string>  $requested
     */
    public static function defaultAccess(array $requested): string
    {
        if ($requested !== [] && ! in_array(Scope::WRITE, $requested, true) && ! in_array(Scope::USE, $requested, true) && ! in_array(Scope::OWN, $requested, true)) {
            return self::ACCESS_READ;
        }

        return self::ACCESS_OPERATE;
    }

    /**
     * @return list<string>
     */
    public static function forStaff(string $access): array
    {
        return $access === self::ACCESS_READ ? [Scope::READ] : [Scope::WRITE];
    }

    /**
     * A client on the shared instance gets its own files and no choice.
     *
     * @return list<string>
     */
    public static function forClient(): array
    {
        return [Scope::OWN];
    }

    /**
     * Whether a grant covers what is asked of it, for a refresh that
     * names a scope: equal or narrower, never wider. `mcp:write` covers
     * `mcp:read`; `mcp:use` is covered by anything.
     *
     * @param  list<string>  $granted
     * @param  list<string>  $requested
     */
    public static function covers(array $granted, array $requested): bool
    {
        foreach ($requested as $one) {
            if ($one === Scope::USE || in_array($one, $granted, true)) {
                continue;
            }

            if ($one === Scope::READ && in_array(Scope::WRITE, $granted, true)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * The words a person reads for a grant, on the connections page.
     *
     * @param  list<string>  $scopes
     */
    public static function describe(array $scopes): string
    {
        if (in_array(Scope::OWN, $scopes, true)) {
            return 'Your own files';
        }

        if (in_array(Scope::WRITE, $scopes, true)) {
            return 'Operate';
        }

        return 'Read';
    }
}
