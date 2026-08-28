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
        $this->attempt($file, fn () => Storage::disk($file->disk)->delete($file->path));

        // Every rendition, for every audience — a deleted file's bytes must
        // not survive on disk because whoever wrote the cleanup only knew
        // about the one copy they had in mind.
        //
        // Attempted separately from the original above, not because the two
        // are unrelated but because they are on different disks: renditions
        // are always local, and Storage::disk() throws outright for a name
        // with no configured driver — which is exactly the state the
        // original's disk is in when this fails at all. Sharing one `try`
        // meant a file whose source disk had been removed kept every cached
        // copy of itself, and nothing looks for those again:
        // OrphanFileScanner skips the rendition directories on purpose.
        $this->attempt($file, function () use ($file): void {
            foreach (ThumbnailGenerator::pathsFor($file->id, $file->mime_type) as $renditionPath) {
                Storage::disk('files')->delete($renditionPath);
            }
        });
    }

    /**
     * Deliberately tolerant, as the class docblock says: the warning is the
     * whole report. Nothing else will find these bytes -- the row is
     * soft-deleted, and OrphanFileScanner::knownPaths() counts a trashed
     * row's path as claimed, so a scan never lists it.
     */
    private function attempt(File $file, callable $work): void
    {
        try {
            $work();
        } catch (Throwable $exception) {
            Log::warning('Could not remove disk bytes for deleted file '.$file->id.': '.$exception->getMessage());
        }
    }
}
