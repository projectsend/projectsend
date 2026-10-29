<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Clients;

use App\Modules\Platform\OfficialLinks;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Reads a Client ID Metadata Document from the URL that is the client's
 * identifier (design §3.1). The server is fetching a URL a stranger
 * chose, so: HTTPS only, public hosts only, two redirects at most and
 * never to another scheme, five seconds, sixty-four kilobytes, JSON.
 */
final class ClientMetadataFetcher
{
    public const MAX_BYTES = 65536;

    public const TIMEOUT_SECONDS = 5;

    public const MAX_REDIRECTS = 2;

    public const MIN_TTL_SECONDS = 300;

    public const MAX_TTL_SECONDS = 86400;

    public function __construct(private readonly PublicAddress $addresses) {}

    /**
     * @return array{document: array<string, mixed>, ttl: int}
     *
     * @throws ClientMetadataUnavailable
     */
    public function fetch(string $url): array
    {
        $hops = 0;
        $origin = self::origin($url);

        while (true) {
            $pin = $this->assertFetchable($url);

            try {
                $response = Http::withoutRedirecting()
                    // The size limit while the body arrives, not after: a
                    // host that streams for the whole timeout is cut off at
                    // the limit instead of being held in a temp file.
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => $pin, CURLOPT_MAXFILESIZE => self::MAX_BYTES]])
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->connectTimeout(self::TIMEOUT_SECONDS)
                    ->accept('application/json')
                    // The edition's own front door, as everywhere else.
                    ->withUserAgent('ProjectSend MCP (+'.rtrim(app(OfficialLinks::class)->website(), '/').')')
                    ->get($url);
            } catch (ConnectionException|TransferException $e) {
                throw new ClientMetadataUnavailable('The client metadata document could not be fetched: '.$e->getMessage());
            }

            if (! $response->redirect()) {
                return ['document' => $this->document($response), 'ttl' => $this->ttl($response)];
            }

            $location = $response->header('Location');

            if ($location === '' || $hops === self::MAX_REDIRECTS) {
                throw new ClientMetadataUnavailable('The client metadata document redirects too many times.');
            }

            // Only to the same host. The document must name the URL it was
            // asked for, so an open redirect on a trusted host would
            // otherwise let a document anywhere borrow that host's name,
            // shown on the consent screen as verified by it.
            if (self::origin($location) !== $origin) {
                throw new ClientMetadataUnavailable('The client metadata document redirects to another host.');
            }

            $url = $location;
            $hops++;
        }
    }

    /** scheme://host:port, lower-cased, or null for anything not absolute. */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower(rtrim($parts['host'], '.')).':'.($parts['port'] ?? 443);
    }

    /**
     * Checks the URL, and returns the CURLOPT_RESOLVE entry that holds the
     * connection to the addresses just checked. Without it, curl looks the
     * name up again, and a name that answers public to the check and
     * private to the connection reaches inside the network
     * (GHSA-q6jm-vwg8-ppx9).
     *
     * @return list<string>
     *
     * @throws ClientMetadataUnavailable
     */
    private function assertFetchable(string $url): array
    {
        // Only curl takes the pin. Any other handler would look the name
        // up itself, so the check would stop meaning anything.
        if (! extension_loaded('curl')) {
            throw new ClientMetadataUnavailable('Client metadata documents need the PHP curl extension.');
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new ClientMetadataUnavailable('A client metadata document is fetched over HTTPS from a plain URL.');
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new ClientMetadataUnavailable('A client metadata document is served on the standard HTTPS port.');
        }

        $addresses = $this->addresses->assertPublic($parts['host']);

        // An address in the URL is not looked up, so there is nothing to pin.
        if (filter_var(trim($parts['host'], '[]'), FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $addresses = array_map(static fn (string $address): string => str_contains($address, ':') ? "[{$address}]" : $address, $addresses);

        return [$parts['host'].':443:'.implode(',', $addresses)];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClientMetadataUnavailable
     */
    private function document(Response $response): array
    {
        if ($response->status() !== 200) {
            throw new ClientMetadataUnavailable('The client metadata document answered '.$response->status().'.');
        }

        $declared = $response->header('Content-Length');

        if ($declared !== '' && (int) $declared > self::MAX_BYTES) {
            throw new ClientMetadataUnavailable('The client metadata document is larger than 64 KB.');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            throw new ClientMetadataUnavailable('The client metadata document is larger than 64 KB.');
        }

        if (! str_contains(strtolower($response->header('Content-Type')), 'json')) {
            throw new ClientMetadataUnavailable('The client metadata document is not JSON.');
        }

        $document = json_decode($body, true);

        if (! is_array($document)) {
            throw new ClientMetadataUnavailable('The client metadata document is not a JSON object.');
        }

        /** @var array<string, mixed> $document */
        return $document;
    }

    /** How long the document may be trusted, from its own headers, within bounds. */
    private function ttl(Response $response): int
    {
        $ttl = null;

        if (preg_match('/max-age=(\d+)/i', $response->header('Cache-Control'), $match) === 1) {
            $ttl = (int) $match[1];
        } elseif (($expires = $response->header('Expires')) !== '' && ($at = strtotime($expires)) !== false) {
            $ttl = $at - time();
        }

        return max(self::MIN_TTL_SECONDS, min(self::MAX_TTL_SECONDS, $ttl ?? self::MIN_TTL_SECONDS));
    }
}
