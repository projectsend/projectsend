<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Files\Models\ZipDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;

class PurgeZipDownloadsCommand extends Command
{
    protected $signature = 'projectsend:purge-zip-downloads';

    protected $description = 'Remove built zip downloads (and their files) older than 24 hours — disposable, regenerable artifacts';

    public function handle(): int
    {
        $stale = ZipDownload::query()->where('created_at', '<', now()->subDay())->get();

        // Listed once up front: the loop only deletes, so nothing it does
        // changes what a later row would match.
        $builtZips = collect(Storage::disk('files')->files('zips'));

        foreach ($stale as $zipDownload) {
            // Every artifact tied to this row's id, not just the recorded
            // path: a build killed before it finished (worker timeout, disk
            // full) leaves a partial archive — and libzip's temp file
            // alongside it — with no path ever written back to the row.
            $artifacts = $builtZips
                ->filter(fn (string $path): bool => str_starts_with(basename($path), $zipDownload->id.'.zip'))
                ->all();

            if ($zipDownload->path !== null) {
                $artifacts[] = $zipDownload->path;
            }

            Storage::disk('files')->delete(array_values(array_unique($artifacts)));

            $zipDownload->delete();
        }

        $swept = $this->sweepUnreferenced();

        $this->info("Purged {$stale->count()} stale zip download(s) and {$swept} unreferenced file(s).");

        return self::SUCCESS;
    }

    /**
     * Rows are what the loop above cleans by, so a file whose row is gone
     * is invisible to it — and a row can vanish without its files:
     * zip_downloads.requested_by cascades on delete, so removing a user
     * takes their rows with it and leaves every archive they built behind.
     * Anything already stranded that way before this command learned to
     * look is in the same position.
     *
     * OrphanFileScanner skips zips/ on purpose — this command owns that
     * directory, so closing the gap belongs here.
     */
    private function sweepUnreferenced(): int
    {
        $disk = Storage::disk('files');
        $cutoff = now()->subDay()->getTimestamp();
        $live = array_flip(ZipDownload::query()->pluck('id')->all());
        $unreferenced = [];

        foreach ($disk->files('zips') as $path) {
            // Both an archive (12.zip) and libzip's temp beside it
            // (12.zip.aB3xY9) lead with the row id they belong to.
            $id = explode('.', basename($path))[0];

            if (ctype_digit($id) && isset($live[(int) $id])) {
                continue;
            }

            try {
                // A day's grace before deleting something no row explains.
                // Nothing here should outlive its row by design, so the
                // wait costs nothing — and it means a file another process
                // has only just put there is never taken out from under it.
                if ($disk->lastModified($path) >= $cutoff) {
                    continue;
                }
            } catch (UnableToRetrieveMetadata) {
                // Gone between listing the directory and asking about it.
                continue;
            }

            $unreferenced[] = $path;
        }

        $disk->delete($unreferenced);

        return count($unreferenced);
    }
}
