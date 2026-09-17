<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Talks to ClamAV's daemon, `clamd`, over a Unix socket or TCP.
 *
 * The protocol is small enough to own: a command is `z<COMMAND>\0`, and
 * INSTREAM is that followed by length-prefixed chunks and a zero-length
 * chunk to finish. Taking a library for this would be more dependency
 * than code.
 *
 * **Three of clamd's own settings decide whether this class can tell the
 * truth**, and without them a file it could not open comes back as `OK`:
 * `AlertExceedsMax`, `AlertEncrypted` and its two companions turn those
 * cases into answers, which arrive here as `Heuristics.Limits.Exceeded.*`
 * and `Heuristics.Encrypted.*` and are mapped below to tooLarge and
 * encrypted rather than to a threat. An encrypted archive full of malware
 * reported as clean is the failure this exists to prevent, so the
 * shipped Docker configuration sets all of them and the documentation
 * says so for manual installs.
 *
 * Nothing here throws for a scanner that is down, slow or misconfigured:
 * the caller has a policy for that, and an exception would read as a bug
 * in the job rather than as the state of somebody's server.
 */
class ClamAvScanner implements VirusScanner
{
    /** 64 KiB — clamd's own read buffer size, and small enough to stream 5 GB without holding it. */
    private const CHUNK = 65536;

    /** Seconds to wait for an answer to VERSION. */
    private const VERSION_TIMEOUT = 10;

    /**
     * What the daemon said it was, the first time this instance asked.
     *
     * Every verdict records the engine and definitions that reached it, so
     * a stored "clean" can be read back against what knew it. Asking on
     * every scan would double the connections; asking once per instance
     * means once per queue job, and the worker is recycled hourly.
     */
    private ?string $engine = null;

    public function __construct(
        private readonly ScanningConfig $config,
    ) {}

    public function scan(mixed $stream, int $size): ScanVerdict
    {
        $max = $this->config->maxScanBytes();

        // Asked before opening a socket: a file this installation has
        // decided not to scan should not spend a connection, and clamd
        // would refuse it anyway once it passed StreamMaxLength.
        if ($max > 0 && $size > $max) {
            return ScanVerdict::tooLarge($this->engine());
        }

        if (! $this->addressIsUsable()) {
            return ScanVerdict::unavailable(__(ScannerAddress::message()));
        }

        $socket = $this->connect();

        if ($socket === null) {
            return ScanVerdict::unavailable(__('The scanner could not be reached at :address.', [
                'address' => $this->config->address(),
            ]));
        }

        try {
            $sent = $this->send($socket, "zINSTREAM\0");

            while ($sent && ! feof($stream)) {
                $chunk = fread($stream, self::CHUNK);

                if ($chunk === false) {
                    return ScanVerdict::unavailable(__('The file could not be read for scanning.'));
                }

                if ($chunk === '') {
                    continue;
                }

                // Big-endian length, then the bytes.
                $sent = $this->send($socket, pack('N', strlen($chunk)).$chunk);
            }

            if ($sent) {
                $this->send($socket, pack('N', 0));
            }

            // Read whether or not every byte went: a write that fails
            // means clamd hung up mid-stream, and it only does that after
            // saying why — usually its own size limit. Treating the
            // failed write as "the scanner is down" instead sent every
            // file over that limit round the retry loop forever, and past
            // the unscannable policy.
            $reply = $this->readReply($socket);
        } catch (Throwable $e) {
            return ScanVerdict::unavailable($e->getMessage());
        } finally {
            fclose($socket);
        }

        if ($reply === null) {
            return ScanVerdict::unavailable(__('The scanner did not answer in time.'));
        }

        return $this->verdictFor($reply, $this->engine());
    }

    public function status(): ScannerStatus
    {
        // Named rather than reported as "no answer". A managed address
        // comes from the environment and never passed the settings
        // screen's validation, so this is the only place it is checked —
        // and the socket would accept a malformed one by reading the
        // digits at the front of the port and ignoring the rest, which is
        // how an address with a typo on the end came to look like it
        // worked.
        if (! $this->addressIsUsable()) {
            return ScannerStatus::unreachable(__(ScannerAddress::message()));
        }

        // A short wait rather than the scan's: VERSION is answered at once
        // by anything that is clamd, and the Test button waits on this.
        $socket = $this->connect(self::VERSION_TIMEOUT);

        if ($socket === null) {
            return ScannerStatus::unreachable(__('No answer from :address.', ['address' => $this->config->address()]));
        }

        try {
            $this->send($socket, "zVERSION\0");
            $reply = $this->readReply($socket);
        } catch (Throwable $e) {
            return ScannerStatus::unreachable($e->getMessage());
        } finally {
            fclose($socket);
        }

        if ($reply === null || $reply === '') {
            return ScannerStatus::unreachable(__('The scanner did not answer in time.'));
        }

        // "ClamAV 1.4.1/27412/Mon Sep 15 09:12:03 2026" — engine,
        // signature database number, and when that database was built.
        // Older builds answer with the engine alone, so every part after
        // the first is optional rather than assumed.
        $parts = explode('/', $reply);

        // Anything listening on the port answers something. Without this a
        // database or a web server "answered", and the first bytes of its
        // greeting were shown as the engine's name.
        if (! str_starts_with($parts[0], 'ClamAV ')) {
            return ScannerStatus::unreachable(__('Something answered at :address, but it is not a ClamAV scanner.', [
                'address' => $this->config->address(),
            ]));
        }

        $definitions = isset($parts[1]) && is_numeric(trim($parts[1])) ? (int) trim($parts[1]) : null;
        $built = null;

        if (isset($parts[2])) {
            try {
                $built = Carbon::parse(trim($parts[2]));
            } catch (Throwable) {
                $built = null;
            }
        }

        return new ScannerStatus(true, trim($parts[0]), $definitions, $built);
    }

    private function verdictFor(string $reply, ?string $engine): ScanVerdict
    {
        // The whole reply, not its last two letters: "clean" is the one
        // answer that hands a file out, so nothing else may be read as it.
        if ($reply === 'stream: OK') {
            return ScanVerdict::clean($engine);
        }

        // "stream: Win.Test.EICAR_HDB-1 FOUND"
        if (str_starts_with($reply, 'stream: ') && str_ends_with($reply, ' FOUND')) {
            $threat = trim(str_replace(['stream:', 'FOUND'], '', $reply));

            // Not threats: clamd's way of saying "I could not look
            // inside". Which one it is decides the file's fate, and both
            // are the installation's policy rather than a detection.
            if (str_contains($threat, 'Heuristics.Encrypted')) {
                return ScanVerdict::encrypted($engine);
            }

            if (str_contains($threat, 'Heuristics.Limits.Exceeded')) {
                return ScanVerdict::tooLarge($engine);
            }

            return ScanVerdict::infected($threat === '' ? 'unknown' : $threat, $engine);
        }

        // "INSTREAM size limit exceeded. ERROR" — the stream was longer
        // than clamd's StreamMaxLength. Same meaning as the heuristic
        // above, reached when this installation's own maximum is the
        // larger of the two.
        if (str_contains($reply, 'size limit exceeded')) {
            return ScanVerdict::tooLarge($engine);
        }

        return ScanVerdict::unavailable($reply);
    }

    /**
     * "ClamAV 1.5.4/28122" — engine and signature database, as recorded
     * against every verdict. Null when the daemon did not say.
     */
    private function engine(): ?string
    {
        if ($this->engine !== null) {
            return $this->engine;
        }

        $status = $this->status();

        if (! $status->reachable || $status->engine === null) {
            return null;
        }

        return $this->engine = $status->definitionsVersion === null
            ? $status->engine
            : $status->engine.'/'.$status->definitionsVersion;
    }

    /** Whether the configured address is one at all — see ScannerAddress. */
    private function addressIsUsable(): bool
    {
        return ScannerAddress::isValid($this->config->address());
    }

    /** @return resource|null */
    private function connect(?int $replyTimeout = null): mixed
    {
        $address = $this->config->address();

        if ($address === '' || ! $this->addressIsUsable()) {
            return null;
        }

        $socket = @stream_socket_client(
            $address,
            $code,
            $message,
            $this->config->connectTimeoutSeconds(),
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            return null;
        }

        // Without this a scanner that accepts the connection and then
        // stops answering holds the worker open indefinitely.
        stream_set_timeout($socket, $replyTimeout ?? $this->config->replyTimeoutSeconds());

        return $socket;
    }

    /**
     * Write all of it, or say that it could not.
     *
     * fwrite() may take part of a buffer and return how much, and on a
     * connection the other end has closed it raises a warning — which the
     * framework's error handler turns into an exception. Silenced and
     * checked here instead, so a hang-up reads as a hang-up and the reply
     * explaining it can still be read.
     *
     * @param  resource  $socket
     */
    private function send(mixed $socket, string $bytes): bool
    {
        while ($bytes !== '') {
            $written = @fwrite($socket, $bytes);

            if ($written === false || $written === 0) {
                return false;
            }

            $bytes = substr($bytes, $written);
        }

        return true;
    }

    /**
     * clamd's replies end with a NUL in `z` mode. Returns null when the
     * socket timed out rather than answered.
     *
     * @param  resource  $socket
     */
    private function readReply(mixed $socket): ?string
    {
        $reply = '';

        while (! feof($socket)) {
            // Silenced for the same reason as send(): a connection clamd
            // has reset raises a warning here, and the framework would
            // turn that into an exception before the loop could stop.
            $byte = @fread($socket, 1);

            if ($byte === false || $byte === '') {
                break;
            }

            if ($byte === "\0") {
                break;
            }

            $reply .= $byte;
        }

        if (stream_get_meta_data($socket)['timed_out']) {
            return null;
        }

        return trim($reply);
    }
}
