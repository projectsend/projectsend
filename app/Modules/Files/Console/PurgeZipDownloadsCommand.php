<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Files\Models\ZipDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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

        $this->info("Purged {$stale->count()} stale zip download(s).");

        return self::SUCCESS;
    }
}
