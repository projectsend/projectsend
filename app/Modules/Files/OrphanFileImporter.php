<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Files\Jobs\ComputeFileChecksumJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\StoreUploadedFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Adopts an orphan in place: a File row for bytes already on disk. Shared
 * by the orphans screen (a ticked selection, in the request) and
 * ImportOrphanFilesJob ("import all", in the background). Which paths are
 * importable is OrphanFileScanner's call.
 */
class OrphanFileImporter
{
    /**
     * Hashing reads every byte. Up to this size that takes a few seconds,
     * well inside a request (30s of PHP) or one chunk of "Import all"
     * (45s). Past it, the row is created without a checksum and
     * ComputeFileChecksumJob fills it in, so a 60 GB file imports at once
     * instead of never (#1836).
     */
    public const INLINE_CHECKSUM_MAX_BYTES = 256 * 1024 * 1024;

    public function __construct(
        private readonly StoreUploadedFile $storeFile,
        private readonly int $inlineChecksumMaxBytes = self::INLINE_CHECKSUM_MAX_BYTES,
    ) {}

    public function import(User $user, string $diskName, string $path): File
    {
        $disk = Storage::disk($diskName);
        $size = $disk->size($path);
        $deferChecksum = $size > $this->inlineChecksumMaxBytes;

        $file = $this->storeFile->create(
            uploader: $user,
            originalName: basename($path),
            path: $path,
            mimeType: $disk->mimeType($path) ?: 'application/octet-stream',
            size: $size,
            checksum: $deferChecksum ? '' : $this->checksumOf($disk, $path),
            folderId: null,
            disk: $diskName,
            action: Action::FileImported,
        );

        if ($deferChecksum) {
            ComputeFileChecksumJob::dispatch($file->id);
        }

        return $file;
    }

    /**
     * Streamed rather than hash_file() on a local path — the only way to
     * checksum a file that might live on a non-local disk (S3 has no
     * local filesystem path to hand hash_file()).
     */
    private function checksumOf(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if ($stream === null) {
            return '';
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
