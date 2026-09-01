<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Modules\Files\Storage\ResolvingUploadDisk;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File as FileSystem;
use Illuminate\Support\Facades\Log;
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
     * Said twice, because a full temp volume can announce itself in the
     * middle of the copy or only when the last buffer is flushed.
     */
    private const WRITE_FAILED = 'Could not assemble the upload: writing to the temporary directory failed.';

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
     * Stream-append parts in order onto the files disk, hashing as we go.
     *
     * The parts stay on disk until the assembled bytes are safely on the
     * target disk. ChunkedUploadsController's completion lock promises that
     * "a later retry still works", and everything that can fail after the
     * concatenation — reopening the copy, a disk refusing the write, the
     * File row itself — happens while the client has nothing but this
     * session to retry with. Unlinking each part as it was consumed left
     * listParts() empty, so every later complete() answered "Upload is
     * incomplete: missing parts" for good.
     *
     * The cost is temp space: peak usage is the whole file twice over
     * (every part, plus the assembled copy) rather than the file plus one
     * part. Both are freed by the abort() below the moment the write lands,
     * and by the failure path the moment it does not.
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

        try {
            [$size, $checksum] = $this->concatenate($session, $parts, $assembledPath);

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
                // The reason is lost by the time it gets here — 'throw' => false
                // means Flysystem swallowed the exception rather than passing it
                // on — so log what was attempted. Which bucket it was is the
                // difference between reading this as "my credentials expired"
                // and "I typed the wrong bucket name", and only the log can say
                // it: the message below is shown to whoever was uploading, which
                // includes clients, and a bucket name is not theirs to see.
                Log::error('Upload could not be written to storage.', [
                    'disk' => $disk,
                    'bucket' => config('filesystems.disks.'.$disk.'.bucket'),
                    'driver' => config('filesystems.disks.'.$disk.'.driver'),
                    'path' => $targetPath,
                ]);

                throw new RuntimeException(
                    'Could not write the assembled upload to the "'.$disk.'" disk. '
                    .'Check the storage backend is reachable and its credentials are still valid.'
                );
            }
        } catch (Throwable $failure) {
            // The half-written copy belongs to this attempt and the next one
            // makes its own; the parts belong to the client, and they are
            // what a retry needs. Deleting the copy here is also the only
            // thing that removes it at all on this path — it used to sit in
            // the session directory until the sweeper came round.
            FileSystem::delete($assembledPath);

            throw $failure;
        }

        $this->abort($session);

        return [
            'path' => $targetPath,
            'disk' => $disk,
            'size' => $size,
            'checksum' => $checksum,
        ];
    }

    /**
     * Concatenate the parts into $assembledPath, returning the byte count
     * and the sha256 of what was written.
     *
     * Every read and every write is checked. They were not, and while a
     * failing fwrite on a full volume is loud in practice — Laravel's
     * error handler turns the warning into an ErrorException — loud there
     * means a 500 carrying a PHP message, where the disk-refused-the-write
     * case a few lines above becomes a sentence the person uploading can
     * act on. A short write arriving without a warning would be worse
     * still: $size and the hash describe the buffer that was read, so an
     * unchecked one yields a truncated file with a checksum matching bytes
     * that were never stored.
     *
     * @param  list<array{PartNumber: int, Size: int, ETag: string}>  $parts
     * @return array{0: int, 1: string}
     */
    private function concatenate(UploadSession $session, array $parts, string $assembledPath): array
    {
        $out = fopen($assembledPath, 'wb');

        if ($out === false) {
            throw new RuntimeException('Could not open assembly target.');
        }

        $hash = hash_init('sha256');
        $size = 0;

        try {
            foreach ($parts as $part) {
                $in = fopen($this->partPath($session, $part['PartNumber']), 'rb');

                if ($in === false) {
                    throw new RuntimeException('Could not read part '.$part['PartNumber'].'.');
                }

                try {
                    while (! feof($in)) {
                        $buffer = fread($in, 1024 * 1024);

                        if ($buffer === false) {
                            throw new RuntimeException('Could not read part '.$part['PartNumber'].'.');
                        }

                        if ($buffer !== '' && @fwrite($out, $buffer) !== strlen($buffer)) {
                            throw new RuntimeException(self::WRITE_FAILED);
                        }

                        hash_update($hash, $buffer);
                        $size += strlen($buffer);
                    }
                } finally {
                    fclose($in);
                }
            }
        } catch (Throwable $failure) {
            fclose($out);

            throw $failure;
        }

        // fclose flushes, so a volume that filled up on the last buffer
        // fails here rather than in the loop.
        if (! fclose($out)) {
            throw new RuntimeException(self::WRITE_FAILED);
        }

        return [$size, hash_final($hash)];
    }

    public function abort(UploadSession $session): void
    {
        FileSystem::deleteDirectory($this->directory($session));
    }

    private function directory(UploadSession $session): string
    {
        return $this->root().'/'.$session->id;
    }

    /**
     * Where part files live while a transfer is in progress.
     *
     * Configurable only so the test suite can hold it apart per parallel
     * worker. This is a real directory rather than a faked disk, and each
     * worker's database restarts session ids at 1, so two workers writing
     * parts land in the same place — and ChunkedUploadsTest's afterEach
     * deletes the whole tree, for everybody. Unset, which is every
     * installation, the path is what it has always been.
     */
    private function root(): string
    {
        $configured = config('projectsend.uploads.parts_path');

        return is_string($configured) && $configured !== ''
            ? rtrim($configured, '/')
            : storage_path('app/uploads-tmp');
    }

    private function partPath(UploadSession $session, int $partNumber): string
    {
        return $this->directory($session).'/'.$partNumber.'.part';
    }
}
