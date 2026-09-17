<?php

declare(strict_types=1);

use App\Modules\Files\Scanning\ClamAvScanner;
use App\Modules\Files\Scanning\ScanOutcome;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/*
 * The real client, against a server that behaves the way clamd does at
 * the edges. A separate process, because the interesting cases are about
 * a connection the other end closes while this one is still writing, and
 * that cannot happen inside one PHP process.
 */

/**
 * Start a stand-in for clamd and point the settings at it.
 *
 * `$instream` is what it does with a scan: "limit" reads a little, says
 * the stream is too long and hangs up, as clamd does at StreamMaxLength;
 * "greeting" answers every connection like a MySQL server would, before
 * anything is asked.
 *
 * @return array{0: resource, 1: resource}
 */
function startFakeClamd(string $instream): array
{
    $script = <<<'PHP'
        $mode = $argv[1];
        $server = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        fwrite(STDOUT, stream_socket_get_name($server, false)."\n");
        fflush(STDOUT);

        for ($i = 0; $i < 4; $i++) {
            $client = @stream_socket_accept($server, 10);
            if ($client === false) {
                break;
            }

            if ($mode === 'greeting') {
                fwrite($client, "J\0\0\0\n8.4.0\0");
                fclose($client);
                continue;
            }

            $command = '';
            while (($byte = fread($client, 1)) !== false && $byte !== '' && $byte !== "\0") {
                $command .= $byte;
            }

            if ($command === 'zVERSION') {
                fwrite($client, "ClamAV 1.5.4/28125/Wed Sep 16 06:24:23 2026\0");
            } else {
                $read = 0;
                while ($read < 200000 && ($chunk = fread($client, 65536)) !== false && $chunk !== '') {
                    $read += strlen($chunk);
                }
                fwrite($client, "INSTREAM size limit exceeded. ERROR\0");
            }

            fclose($client);
        }
        PHP;

    $process = proc_open([PHP_BINARY, '-r', $script, '--', $instream], [1 => ['pipe', 'w']], $pipes);
    assert(is_resource($process));

    $address = trim((string) fgets($pipes[1]));

    app(Settings::class)->set(Setting::VirusScanningEnabled, true);
    app(Settings::class)->set(Setting::VirusScannerAddress, "tcp://{$address}");
    app(Settings::class)->set(Setting::VirusScanMaxSizeMb, 512);

    return [$process, $pipes[1]];
}

/** @param  array{0: resource, 1: resource}  $server */
function stopFakeClamd(array $server): void
{
    fclose($server[1]);
    proc_terminate($server[0]);
    proc_close($server[0]);
}

test('a stream clamd hangs up on for being too long is too large, not a scanner that is down', function () {
    $server = startFakeClamd('limit');

    // Big enough that the writes are still going when the server closes.
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, str_repeat('a', 8 * 1024 * 1024));
    rewind($stream);

    try {
        $verdict = app(ClamAvScanner::class)->scan($stream, 8 * 1024 * 1024);
    } finally {
        fclose($stream);
        stopFakeClamd($server);
    }

    // Read as "unavailable", this file went round the retry loop and past
    // the unscannable policy.
    expect($verdict->outcome)->toBe(ScanOutcome::TooLarge);
});

test('a service that is not clamd is not reported as a scanner', function () {
    $server = startFakeClamd('greeting');

    try {
        $status = app(ClamAvScanner::class)->status();
    } finally {
        stopFakeClamd($server);
    }

    // It used to be "reachable", with the first bytes of the greeting
    // shown as the engine's name.
    expect($status->reachable)->toBeFalse()
        ->and($status->error)->toContain('not a ClamAV scanner');
});
