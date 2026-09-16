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

        $socket = $this->connect();

        if ($socket === null) {
            return ScanVerdict::unavailable(__('The scanner could not be reached at :address.', [
                'address' => $this->config->address(),
            ]));
        }

        try {
            fwrite($socket, "zINSTREAM\0");

            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK);

                if ($chunk === false) {
                    return ScanVerdict::unavailable(__('The file could not be read for scanning.'));
                }

                if ($chunk === '') {
                    continue;
                }

                // Big-endian length, then the bytes. A short write here
                // means clamd hung up mid-stream — usually its own size
                // limit — and the reply below says which.
                if (fwrite($socket, pack('N', strlen($chunk)).$chunk) === false) {
                    break;
                }
            }

            fwrite($socket, pack('N', 0));

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
        $socket = $this->connect();

        if ($socket === null) {
            return ScannerStatus::unreachable(__('No answer from :address.', ['address' => $this->config->address()]));
        }

        try {
            fwrite($socket, "zVERSION\0");
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
        if (str_ends_with($reply, 'OK')) {
            return ScanVerdict::clean($engine);
        }

        // "stream: Win.Test.EICAR_HDB-1 FOUND"
        if (str_ends_with($reply, 'FOUND')) {
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

    /** @return resource|null */
    private function connect(): mixed
    {
        $address = $this->config->address();

        if ($address === '') {
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
        stream_set_timeout($socket, $this->config->replyTimeoutSeconds());

        return $socket;
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
            $byte = fread($socket, 1);

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
