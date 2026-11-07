<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Clients;

/**
 * Where a client may be sent back to (design §3.3): HTTPS, a loopback
 * address, or a scheme of the client's own. Matched exactly against
 * what was registered — the one variation allowed is the port of a
 * loopback address, which RFC 8252 lets a program pick at run time.
 */
final class RedirectUris
{
    public const MAX_LENGTH = 2048;

    /**
     * Schemes a browser runs or reads instead of handing to a program.
     * Both answers to the consent send the browser to the redirect, so a
     * `javascript:` one ran on this site the moment somebody clicked Deny.
     */
    private const REFUSED_SCHEMES = ['javascript', 'data', 'vbscript', 'file', 'blob', 'about', 'filesystem', 'view-source'];

    public static function isAcceptable(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > self::MAX_LENGTH || str_contains($uri, '#')) {
            return false;
        }

        // PHP and a browser must agree on the host, because the consent
        // screen shows PHP's answer and the browser goes to its own. They
        // disagree on a backslash: `https://evil.com\@claude.ai/cb` is
        // claude.ai to parse_url and evil.com to a browser. A user:pass
        // part has no use in a redirect and is the other half of that
        // trick, so neither is accepted, encoded or not. Nor is anything a
        // browser would strip before reading the URL.
        if (preg_match('/[\\\\\x00-\x20\x7f]|%5c/i', $uri) === 1) {
            return false;
        }

        $parts = parse_url($uri);

        if ($parts === false || ! isset($parts['scheme']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme === 'https') {
            return isset($parts['host']) && $parts['host'] !== '';
        }

        if ($scheme === 'http') {
            return self::isLoopbackHost($parts['host'] ?? '');
        }

        if (in_array($scheme, self::REFUSED_SCHEMES, true)) {
            return false;
        }

        // A scheme of the client's own, `cursor://…`: accepted as registered.
        return preg_match('/^[a-z][a-z0-9+.\-]*$/', $scheme) === 1;
    }

    public static function isLoopback(string $uri): bool
    {
        $parts = parse_url($uri);

        return $parts !== false
            && strtolower($parts['scheme'] ?? '') === 'http'
            && self::isLoopbackHost($parts['host'] ?? '');
    }

    public static function matches(string $registered, string $presented): bool
    {
        if (hash_equals($registered, $presented)) {
            return true;
        }

        if (! self::isLoopback($registered) || ! self::isLoopback($presented)) {
            return false;
        }

        $a = parse_url($registered);
        $b = parse_url($presented);

        if ($a === false || $b === false) {
            return false;
        }

        foreach (['scheme', 'host', 'path', 'query'] as $part) {
            if (($a[$part] ?? null) !== ($b[$part] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public static function host(string $uri): ?string
    {
        $host = parse_url($uri, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    private static function isLoopbackHost(string $host): bool
    {
        return in_array(strtolower($host), ['127.0.0.1', '[::1]', 'localhost'], true);
    }
}
