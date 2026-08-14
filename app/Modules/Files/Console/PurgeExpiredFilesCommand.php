<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;

class PurgeExpiredFilesCommand extends Command
{
    protected $signature = 'projectsend:purge-expired-files';

    protected $description = 'Permanently delete files whose expiry grace period has passed (runs daily)';

    public function handle(Settings $settings, ActivityLogger $activity): int
    {
        if ($settings->get(Setting::ExpiredFilesAutoDeleteEnabled) !== true) {
            $this->info('Auto-delete of expired files is disabled.');

            return self::SUCCESS;
        }

        $graceDays = (int) $settings->get(Setting::ExpiredFilesDeleteAfterDays);

        $due = File::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->subDays($graceDays))
            ->get();

        foreach ($due as $file) {
            $activity->logSystem(Action::ExpiredFileDeleted, ['name' => $file->name, 'id' => $file->id]);
            // Soft delete — File::booted()'s `deleted` hook already runs
            // FileDiskCleanup, same as a manual delete from the editor.
            $file->delete();
        }

        $this->info("Deleted {$due->count()} expired file(s).");

        return self::SUCCESS;
    }
}
