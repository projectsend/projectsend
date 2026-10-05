<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Models\User;
use App\Modules\Files\OrphanFileImporter;
use App\Modules\Files\OrphanFileScanner;
use App\Modules\Files\OrphanImportProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Import all" on the orphans screen. Thousands of files, each hashed in
 * full, run for minutes, which is far past what one web request is
 * allowed (see OrphanFilesController::import).
 *
 * The work is cut into chunks of about $budgetSeconds. Each chunk scans
 * the disk again and imports what is still orphaned until its time is up,
 * then queues the next chunk. That keeps every job well inside the default
 * worker's 60s timeout and the queue's 90s retry_after, so it can share
 * the default queue (no extra worker to deploy). Mail queued in the
 * meantime goes out between chunks rather than waiting behind the whole
 * import. Because each chunk rescans, a file already adopted is never
 * offered twice, and a run that dies part-way resumes from what is left.
 */
class ImportOrphanFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Not retried as such: the next "Import all" (or the next chunk)
     * rescans and carries on from where this one stopped. failed()
     * records why it stopped.
     */
    public int $tries = 1;

    public function __construct(
        private readonly int $userId,
        private readonly ?string $search,
        private readonly int $budgetSeconds = 45,
    ) {}

    public function handle(OrphanFileScanner $scanner, OrphanFileImporter $importer, OrphanImportProgress $progress): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            $progress->fail('The account that started the import no longer exists.');

            return;
        }

        // Asked at every chunk, not only when the run was started: a run
        // can outlast the access of the person who began it, and each
        // chunk adopts files in their name.
        if (! $user->active || ! $user->isStaff() || ! $user->can('import_orphans')) {
            $progress->fail('The account that started the import can no longer import files.');

            return;
        }

        $deadline = microtime(true) + $this->budgetSeconds;

        foreach ($scanner->importable($user, $this->search) as $i => $item) {
            // At least one file per chunk, so a run always moves forward.
            if ($i > 0 && microtime(true) >= $deadline) {
                self::dispatch($this->userId, $this->search, $this->budgetSeconds);

                return;
            }

            if ($this->claimAndImport($importer, $user, $item['disk'], $item['path'])) {
                $progress->advance();
            }
        }

        $progress->finish();
    }

    /**
     * Adopt one path, unless another chunk has it or already did.
     *
     * Chunks normally run one after another, but a run that stalled and a
     * new one started after it can both have chunks queued, and with more
     * than one worker two chunks scanning at once would both adopt the
     * same path: two rows on one set of bytes, where deleting either
     * deletes the other's file. The scan alone cannot prevent that, since
     * hashing a large file leaves seconds between seeing a path and
     * writing its row. So each path is claimed first, and checked for a
     * row inside the claim. A path somebody else holds is left to them.
     */
    private function claimAndImport(OrphanFileImporter $importer, User $user, string $disk, string $path): bool
    {
        return (bool) Cache::lock('orphan-files-import:'.sha1($disk.'|'.$path), 600)->get(function () use ($importer, $user, $disk, $path): bool {
            if (File::withTrashed()->where('disk', $disk)->where('path', $path)->exists()) {
                return false;
            }

            $importer->import($user, $disk, $path);

            return true;
        });
    }

    /**
     * The page shows a plain sentence; the exception goes to the log. A
     * storage error can name a bucket, an endpoint or a path, which is
     * for whoever reads the log rather than for the screen.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('The background orphan import failed.', ['exception' => $exception]);

        app(OrphanImportProgress::class)->fail('An error stopped the import. The details are in the application log.');
    }
}
