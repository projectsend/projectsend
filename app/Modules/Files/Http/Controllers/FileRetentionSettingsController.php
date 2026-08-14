<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Whether expired files and/or orphan files are automatically deleted,
 * and how long each waits before doing so — consumed by
 * PurgeExpiredFilesCommand and PurgeOrphanFilesCommand.
 */
class FileRetentionSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly ExternalStorageConfigApplier $externalStorage,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('system/settings/file-retention', [
            'expired_files_auto_delete_enabled' => $this->settings->get(Setting::ExpiredFilesAutoDeleteEnabled),
            'expired_files_delete_after_days' => $this->settings->get(Setting::ExpiredFilesDeleteAfterDays),
            'orphan_files_auto_delete_enabled' => $this->settings->get(Setting::OrphanFilesAutoDeleteEnabled),
            'orphan_files_delete_after_days' => $this->settings->get(Setting::OrphanFilesDeleteAfterDays),
            // Orphan auto-delete only ever operates on the local disk — the
            // frontend disables those two fields entirely while this is
            // true, and update() below ignores changes to them for the
            // same reason.
            'external_storage_active' => $this->externalStorage->isActive(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expired_files_auto_delete_enabled' => ['required', 'boolean'],
            'expired_files_delete_after_days' => ['required', 'integer', 'min:0'],
            'orphan_files_auto_delete_enabled' => ['required', 'boolean'],
            'orphan_files_delete_after_days' => ['required', 'integer', 'min:0'],
        ]);

        $this->settings->set(Setting::ExpiredFilesAutoDeleteEnabled, $validated['expired_files_auto_delete_enabled']);
        $this->settings->set(Setting::ExpiredFilesDeleteAfterDays, (int) $validated['expired_files_delete_after_days']);

        // Defense in depth matching the disabled UI: these two fields are
        // hardcoded off while external storage is active (see
        // PurgeOrphanFilesCommand), so a request reaching this endpoint
        // directly can't change them either — leave whatever is already
        // stored untouched, same "ignore it if not permitted right now"
        // shape as FilesController::update()'s upload_public gate.
        if (! $this->externalStorage->isActive()) {
            $this->settings->set(Setting::OrphanFilesAutoDeleteEnabled, $validated['orphan_files_auto_delete_enabled']);
            $this->settings->set(Setting::OrphanFilesDeleteAfterDays, (int) $validated['orphan_files_delete_after_days']);
        }

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'file_retention']);

        return back();
    }
}
