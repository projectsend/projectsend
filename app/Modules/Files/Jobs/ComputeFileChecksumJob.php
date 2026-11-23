<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Modules\Files\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * The SHA-256 of a file that was too large to hash when its row was
 * created. Importing a 60 GB orphan used to hash it inside the request,
 * which PHP cuts off at 30 seconds, so the file could never be imported
 * at all (#1836). See OrphanFileImporter.
 *
 * Until this runs the row's checksum is '', which the rest of the
 * application already reads as "not known": ScanFileJob does not match
 * on it, and the API returns it as it is.
 */
class ComputeFileChecksumJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Reading tens of gigabytes takes minutes. An hour, like a zip build.
     */
    public int $timeout = 3600;

    /**
     * More than one, because the queue's retry_after (90s) is far shorter
     * than a long read: the job can be handed out a second time while the
     * first run is still going. The second run finds the checksum written
     * and stops, so that is harmless, as long as it is allowed to run.
     */
    public int $tries = 3;

    public function __construct(
        public readonly int $fileId,
    ) {
        // The queue for long jobs: every shipped topology already has a
        // worker on it with room for an hour (see BuildZipDownloadJob),
        // and on `default` this would hold up every email behind it.
        $this->onQueue('zips');
    }

    public function handle(): void
    {
        $file = File::withTrashed()->find($this->fileId);

        if ($file === null || $file->checksum !== '') {
            return;
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);

        if ($stream === null) {
            return;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        // Written without touching updated_at or firing model events: a
        // checksum arriving is not somebody editing the file.
        File::withTrashed()
            ->whereKey($file->id)
            ->where('checksum', '')
            ->toBase()
            ->update(['checksum' => hash_final($context)]);
    }
}
