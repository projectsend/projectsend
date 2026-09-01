<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Delivery\FileDelivery;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Limits that apply when files leave the installation rather than when
 * they arrive. Only the zip cap for now — consumed by
 * ZipDownloadsController and BuildZipDownloadJob.
 */
class DownloadSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly FileDelivery $delivery,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('system/settings/downloads', [
            'max_zip_download_size_mb' => $this->settings->get(Setting::MaxZipDownloadSizeMb),
            // Not a setting, and shown here because this is where somebody
            // coming from v1 looks for one: v1 had a "Download method"
            // dropdown on its uploads options screen. It is an environment
            // variable now rather than a stored setting, because it
            // describes the server the installation is running on rather
            // than a preference — a value in the database can be restored
            // onto a different server and be wrong there.
            'file_delivery' => $this->delivery->describe(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Same ceiling as the upload size field: a megabyte figure
            // large enough to be meaningless is the same as unlimited,
            // which 0 already says more clearly.
            'max_zip_download_size_mb' => ['required', 'integer', 'min:0', 'max:1048576'],
        ]);

        $this->settings->set(Setting::MaxZipDownloadSizeMb, (int) $validated['max_zip_download_size_mb']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'downloads']);

        return back();
    }
}
