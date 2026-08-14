<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Files\Uploads\LocalPartStore;
use App\Modules\Files\Uploads\UploadSession;
use Illuminate\Console\Command;

class PurgeStaleUploadsCommand extends Command
{
    protected $signature = 'projectsend:purge-stale-uploads';

    protected $description = 'Remove chunked-upload sessions (and their temp parts) older than 24 hours';

    public function handle(LocalPartStore $parts): int
    {
        $stale = UploadSession::query()->where('created_at', '<', now()->subDay())->get();

        foreach ($stale as $session) {
            $parts->abort($session);
            $session->delete();
        }

        $this->info("Purged {$stale->count()} stale upload session(s).");

        return self::SUCCESS;
    }
}
