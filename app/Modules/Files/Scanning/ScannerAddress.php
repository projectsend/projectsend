<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

/**
 * Whether a scanner address is one, and not merely one PHP will accept.
 *
 * `stream_socket_client()` reads a port the way `atoi` does: it takes the
 * digits at the front and ignores whatever follows. So
 * `tcp://clamav:3310djlkasjdlk` connects happily to port 3310, and an
 * address with a typo on the end is saved, tested, and reported as
 * working — until the day something parses it differently. Meanwhile
 * `tcp://clamav:33101` goes somewhere else entirely and fails, so the
 * feedback an operator gets is inconsistent with the mistake they made.
 *
 * This refuses both, and says so while the field is still on screen.
 */
final class ScannerAddress
{
    /**
     * A TCP address: host, then a port of one to five digits and nothing
     * after it. The host is a hostname, an IPv4 address, or an IPv6
     * address in brackets — the three forms PHP itself accepts.
     */
    private const TCP = '#^tcp://(?:\[[0-9a-fA-F:]+\]|[a-zA-Z0-9._-]+):([0-9]{1,5})$#';

    /** A Unix socket: an absolute path, and nothing clever. */
    private const UNIX = '#^unix://(/[^\x00]+)$#';

    public static function isValid(string $address): bool
    {
        $address = trim($address);

        if (preg_match(self::UNIX, $address) === 1) {
            return true;
        }

        if (preg_match(self::TCP, $address, $matches) !== 1) {
            return false;
        }

        $port = (int) $matches[1];

        return $port >= 1 && $port <= 65535;
    }

    /**
     * English, and the translation key: what to type instead.
     */
    public static function message(): string
    {
        return 'Enter the scanner as tcp://host:3310 or unix:///path/to/clamd.sock.';
    }
}
