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

        $deadline = microtime(true) + $this->budgetSeconds;

        foreach ($scanner->importable($user, $this->search) as $i => $item) {
            // At least one file per chunk, so a run always moves forward.
            if ($i > 0 && microtime(true) >= $deadline) {
                self::dispatch($this->userId, $this->search, $this->budgetSeconds);

                return;
            }

            $importer->import($user, $item['disk'], $item['path']);
            $progress->advance();
        }

        $progress->finish();
    }

    public function failed(Throwable $exception): void
    {
        app(OrphanImportProgress::class)->fail($exception->getMessage());
    }
}
