<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\OrphanFileScanner;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOrphanFilesCommand extends Command
{
    protected $signature = 'projectsend:purge-orphan-files';

    protected $description = 'Permanently delete local-disk orphan files found longer ago than the configured grace period (runs daily; never touches external storage)';

    public function handle(
        Settings $settings,
        ActivityLogger $activity,
        OrphanFileScanner $scanner,
        ExternalStorageConfigApplier $externalStorage,
    ): int {
        // Hardcoded off whenever external storage is active, regardless
        // of the stored setting — this feature only ever operates on the
        // local disk, on purpose (see FileRetentionSettingsController).
        if ($externalStorage->isActive()) {
            $this->info('External storage is active — orphan auto-delete only ever runs against local storage, so it is skipped.');

            return self::SUCCESS;
        }

        if ($settings->get(Setting::OrphanFilesAutoDeleteEnabled) !== true) {
            $this->info('Auto-delete of orphan files is disabled.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $settings->get(Setting::OrphanFilesDeleteAfterDays));
        $deleted = 0;

        foreach ($scanner->scan() as $orphan) {
            // Belt and suspenders: scannedDisks() only ever includes
            // 'files_external' when external storage is active, and we
            // already returned above when it is — but filter explicitly
            // rather than relying on that alone.
            if ($orphan['disk'] !== 'files' || $orphan['last_modified'] > $cutoff->timestamp) {
                continue;
            }

            // Re-validate right before deleting — same defensive re-check
            // the controller's own destroy() does, in case something
            // claimed this path since the scan ran a moment ago.
            if (! $scanner->isOrphan($orphan['disk'], $orphan['path'])) {
                continue;
            }

            Storage::disk($orphan['disk'])->delete($orphan['path']);
            $activity->logSystem(Action::OrphanFileAutoDeleted, ['name' => basename($orphan['path']), 'disk' => $orphan['disk']]);
            $deleted++;
        }

        $this->info("Deleted {$deleted} orphan file(s).");

        return self::SUCCESS;
    }
}
