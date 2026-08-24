<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails;

use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A real path on this machine for a stored file, so that something which
 * can only work on local bytes — image and video rendering, all of which
 * shells out or hands a path to a C library — can work on any file
 * whatever disk it lives on.
 *
 * A local file is used where it lies. Anything else is stream-copied to a
 * temp file and removed afterwards.
 *
 * The callback shape is the point. This started as a private method on
 * one controller that returned a path and left the caller to unlink it,
 * and the second place that needed it did not call it at all — it passed
 * the *local* disk's path() for a file on external storage, which is a
 * path that does not exist, so every public-listing thumbnail of an
 * externally stored file failed. Handing back a path is an invitation to
 * both of those mistakes; a closure that owns the lifetime is not.
 */
class LocalSourceFile
{
    /**
     * @template TReturn
     *
     * @param  callable(string): TReturn  $work
     * @return TReturn
     */
    public function use(File $file, callable $work): mixed
    {
        if ($file->disk === 'files') {
            return $work(Storage::disk('files')->path($file->path));
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'thumb-src-');

        if ($tempPath === false) {
            throw new RuntimeException('Could not create a temp file for '.$file->original_name);
        }

        try {
            $this->copyDown($file, $tempPath);

            return $work($tempPath);
        } finally {
            @unlink($tempPath);
        }
    }

    private function copyDown(File $file, string $tempPath): void
    {
        $stream = Storage::disk($file->disk)->readStream($file->path);
        $out = fopen($tempPath, 'wb');

        if ($stream === null || $out === false) {
            if (is_resource($out)) {
                fclose($out);
            }

            throw new RuntimeException('Could not read '.$file->original_name.' from its storage disk.');
        }

        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
