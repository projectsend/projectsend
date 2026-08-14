<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Modules\Files\Models\File;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Removes a deleted file's bytes from disk — its original upload (on
 * whichever disk it actually lives on) plus every cached rendition of
 * it, one per ImageAudience and ImageRendition (always local, regardless of the source
 * disk — see FileThumbnailController). Deliberately tolerant of storage failures: a
 * misconfigured or since-rotated external storage backend must never
 * turn a routine "delete file" click into a 500 — the DB soft-delete is
 * the part the user actually sees (the file disappears from every
 * list), and it must always succeed regardless of what happens here.
 */
class FileDiskCleanup
{
    public function delete(File $file): void
    {
        try {
            Storage::disk($file->disk)->delete($file->path);

            // Every rendition, for every audience — a deleted file's bytes
            // must not survive on disk because whoever wrote the cleanup
            // only knew about the one copy they had in mind.
            foreach (ThumbnailGenerator::pathsFor($file->id, $file->mime_type) as $renditionPath) {
                Storage::disk('files')->delete($renditionPath);
            }
        } catch (Throwable $exception) {
            Log::warning('Could not remove disk bytes for deleted file '.$file->id.': '.$exception->getMessage());
        }
    }
}
