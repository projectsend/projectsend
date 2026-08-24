<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Modules\Files\Storage\ResolvingUploadDisk;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File as FileSystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * Part storage for installs without object storage (any VPS / mounted
 * volume): parts arrive as signed PUTs to the app, land in a temp
 * session directory, and are stream-assembled onto whichever disk the
 * ResolvingUploadDisk event resolves (local 'files' by default; the
 * external storage settings, when active, redirect this to
 * 'files_external') with the sha256 computed during the single
 * concatenation pass.
 *
 * The cloud edition replaces this with an S3 implementation handing
 * out real presigned part URLs behind the same contract.
 */
class LocalPartStore
{
    /**
     * The route name is a parameter because the same flow is mounted twice:
     * once on the session-authenticated web routes for the browser, once on
     * the token-authenticated API routes. The signature is over the URL, so
     * it has to be minted against the route the caller will actually PUT to.
     */
    public function signPartUrl(UploadSession $session, int $partNumber, string $routeName = 'uploads.parts.put'): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes(30),
            ['session' => $session->id, 'part' => $partNumber],
        );
    }

    /**
     * $maxBytes is enforced while copying, not just from Content-Length:
     * that header can be absent or untrue under chunked transfer encoding,
     * so the byte count during the copy is the only figure worth trusting.
     * An over-long part is discarded rather than truncated — a truncated
     * part would assemble into a silently corrupt file.
     *
     * @param  resource  $stream
     */
    public function storePart(UploadSession $session, int $partNumber, $stream, ?int $maxBytes = null): string
    {
        $directory = $this->directory($session);

        try {
            FileSystem::ensureDirectoryExists($directory);
        } catch (Throwable $e) {
            // mkdir() failures surface as promoted warnings. The raw message
            // — "mkdir(): Permission denied" with a framework stack trace —
            // was the single most opaque failure QA hit: name the directory
            // and the likely fix instead, so the log line is actionable, and
            // keep the original as the previous exception.
            throw new RuntimeException(sprintf(
                '%s could not be created — uploads cannot accept bytes.'
                .' Almost always ownership: make storage/ writable by the user'
                .' the app runs as (in the Docker image: chown -R www-data:www-data storage).',
                $directory,
            ), previous: $e);
        }

        $path = $this->partPath($session, $partNumber);

        $out = fopen($path, 'wb');

        if ($out === false) {
            throw new RuntimeException(sprintf('Could not open part file %s for writing.', $path));
        }

        if ($maxBytes === null) {
            stream_copy_to_stream($stream, $out);
            fclose($out);

            return md5_file($path) ?: '';
        }

        $written = 0;

        while (! feof($stream)) {
            $buffer = fread($stream, 1024 * 1024);

            if ($buffer === false || $buffer === '') {
                break;
            }

            $written += strlen($buffer);

            if ($written > $maxBytes) {
                fclose($out);
                @unlink($path);

                throw new PartTooLargeException('Upload part exceeds the maximum part size.');
            }

            fwrite($out, $buffer);
        }

        fclose($out);

        return md5_file($path) ?: '';
    }

    /**
     * @return list<array{PartNumber: int, Size: int, ETag: string}>
     */
    public function listParts(UploadSession $session): array
    {
        $directory = $this->directory($session);

        if (! is_dir($directory)) {
            return [];
        }

        $parts = [];

        foreach (glob($directory.'/*.part') ?: [] as $path) {
            $number = (int) basename($path, '.part');
            $parts[$number] = [
                'PartNumber' => $number,
                'Size' => (int) filesize($path),
                'ETag' => md5_file($path) ?: '',
            ];
        }

        ksort($parts);

        return array_values($parts);
    }

    /**
     * Stream-append parts in order onto the files disk, hashing as we
     * go. Peak temp usage ≈ file size + one part (parts are unlinked
     * as they are consumed).
     *
     * @return array{path: string, disk: string, size: int, checksum: string}
     */
    public function assemble(UploadSession $session, string $targetPath): array
    {
        $parts = $this->listParts($session);

        $expected = range(1, count($parts));
        $actual = array_column($parts, 'PartNumber');

        if ($parts === [] || $actual !== $expected) {
            throw new RuntimeException('Upload is incomplete: missing parts.');
        }

        $assembledPath = $this->directory($session).'/assembled';
        $out = fopen($assembledPath, 'wb');

        if ($out === false) {
            throw new RuntimeException('Could not open assembly target.');
        }

        $hash = hash_init('sha256');
        $size = 0;

        foreach ($parts as $part) {
            $partPath = $this->partPath($session, $part['PartNumber']);
            $in = fopen($partPath, 'rb');

            if ($in === false) {
                fclose($out);
                throw new RuntimeException('Could not read part '.$part['PartNumber'].'.');
            }

            while (! feof($in)) {
                $buffer = fread($in, 1024 * 1024);

                if ($buffer === false) {
                    break;
                }

                fwrite($out, $buffer);
                hash_update($hash, $buffer);
                $size += strlen($buffer);
            }

            fclose($in);
            unlink($partPath);
        }

        fclose($out);

        $readStream = fopen($assembledPath, 'rb');

        if ($readStream === false) {
            throw new RuntimeException('Could not reopen assembled file.');
        }

        $diskEvent = new ResolvingUploadDisk($session->user);
        Event::dispatch($diskEvent);
        $disk = $diskEvent->disk;

        $written = Storage::disk($disk)->writeStream($targetPath, $readStream);

        if (is_resource($readStream)) {
            fclose($readStream);
        }

        // The disks are configured with 'throw' => false, so a refused
        // write is a `false` return rather than an exception — and the
        // caller goes on to record a File row for bytes that were never
        // stored. Losing an upload silently is worse than failing it, and
        // this is the only place that can tell the difference: a real
        // instance of it was a GCS bucket rejecting the adapter's ACL,
        // which looked exactly like a successful upload.
        if ($written === false) {
            throw new RuntimeException(
                'Could not write the assembled upload to the "'.$disk.'" disk. '
                .'Check the storage backend is reachable and its credentials are still valid.'
            );
        }

        $this->abort($session);

        return [
            'path' => $targetPath,
            'disk' => $disk,
            'size' => $size,
            'checksum' => hash_final($hash),
        ];
    }

    public function abort(UploadSession $session): void
    {
        FileSystem::deleteDirectory($this->directory($session));
    }

    private function directory(UploadSession $session): string
    {
        return storage_path('app/uploads-tmp/'.$session->id);
    }

    private function partPath(UploadSession $session, int $partNumber): string
    {
        return $this->directory($session).'/'.$partNumber.'.part';
    }
}
