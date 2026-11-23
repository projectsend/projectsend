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
        $purged = 0;

        foreach ($stale as $session) {
            // A session being assembled right now is not abandoned, however
            // old it is. The next run looks at it again (#1842).
            $lock = $session->completionLock(30);

            if (! $lock->get()) {
                continue;
            }

            try {
                $parts->abort($session);
                $session->delete();
                $purged++;
            } finally {
                $lock->release();
            }
        }

        $this->info("Purged {$purged} stale upload session(s).");

        return self::SUCCESS;
    }
}
