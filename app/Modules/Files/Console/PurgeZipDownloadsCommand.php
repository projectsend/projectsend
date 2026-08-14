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

        foreach ($stale as $zipDownload) {
            if ($zipDownload->path !== null) {
                Storage::disk('files')->delete($zipDownload->path);
            }

            $zipDownload->delete();
        }

        $this->info("Purged {$stale->count()} stale zip download(s).");

        return self::SUCCESS;
    }
}
