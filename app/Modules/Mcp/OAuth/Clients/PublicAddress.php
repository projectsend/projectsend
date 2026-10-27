<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Clients;

/**
 * Whether a hostname the server is about to connect to is somebody on
 * the public internet, and not this machine, this network or the cloud
 * metadata service. Asked before every connection and again after every
 * redirect (design §3.1), because a `client_id` is a URL a stranger chose.
 *
 * The resolver is injectable so the tests never touch DNS.
 */
final class PublicAddress
{
    /** @var (callable(string): list<string>)|null */
    private $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  hostname → IP addresses
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * The addresses the host resolved to, every one of them public. The
     * caller connects to these and nothing else: a second lookup could get
     * a different answer (DNS rebinding, GHSA-q6jm-vwg8-ppx9).
     *
     * @return non-empty-list<string>
     *
     * @throws ClientMetadataUnavailable
     */
    public function assertPublic(string $host): array
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new ClientMetadataUnavailable("\"{$host}\" is not a public host.");
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            throw new ClientMetadataUnavailable("\"{$host}\" does not resolve.");
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                throw new ClientMetadataUnavailable("\"{$host}\" resolves to a private address.");
            }
        }

        return $addresses;
    }

    public static function isPublic(string $address): bool
    {
        // An IPv4 address wrapped in IPv6 is the IPv4 address.
        if (str_starts_with(strtolower($address), '::ffff:')) {
            $address = substr($address, 7);
        }

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE;

        return filter_var($address, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return ($this->resolver)($host);
        }

        $addresses = [];

        foreach (gethostbynamel($host) ?: [] as $ipv4) {
            $addresses[] = $ipv4;
        }

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses;
    }
}
