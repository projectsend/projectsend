<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Uploads\UploadTypeRestriction;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UploadSettingsController extends Controller
{
    /**
     * Extensions that are never safe to allow, regardless of what an
     * admin configures — server-executable or interpretable by the web
     * server/CGI, plus browser-renderable-with-script types (htm/html/svg
     * — FileThumbnailController::preview() serves files inline using the
     * stored mime type). Saving one of these flashes a warning rather
     * than blocking the save, matching v1's UX for the same list.
     */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'msi', 'bat', 'cmd', 'com', 'scr',
        'vbs', 'vbe', 'ps1', 'jar', 'war', 'jsp', 'jspx', 'asp', 'aspx', 'ashx', 'dll', 'so',
        'htaccess', 'htm', 'html', 'svg', 'swf', 'wasm',
    ];

    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(): Response
    {
        $allowedExtensions = $this->settings->get(Setting::AllowedUploadExtensions);

        return Inertia::render('system/settings/uploads', [
            'max_file_size_mb' => $this->settings->get(Setting::MaxFileSizeMb),
            'upload_type_restriction' => $this->settings->get(Setting::UploadTypeRestriction),
            'allowed_upload_extensions' => is_array($allowedExtensions) ? $allowedExtensions : [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_file_size_mb' => ['required', 'integer', 'min:0', 'max:1048576'],
            'upload_type_restriction' => ['required', Rule::enum(UploadTypeRestriction::class)],
            'allowed_upload_extensions' => ['required', 'array', 'min:1'],
            'allowed_upload_extensions.*' => ['string', 'max:15', 'regex:/^[a-z0-9]+$/i'],
        ]);

        $allowedExtensions = array_values(array_unique(array_map(
            fn (string $extension): string => strtolower($extension),
            $validated['allowed_upload_extensions'],
        )));

        $this->settings->set(Setting::MaxFileSizeMb, (int) $validated['max_file_size_mb']);
        $this->settings->set(Setting::UploadTypeRestriction, $validated['upload_type_restriction']);
        $this->settings->set(Setting::AllowedUploadExtensions, $allowedExtensions);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'uploads']);

        $dangerous = array_intersect($allowedExtensions, self::DANGEROUS_EXTENSIONS);

        if ($dangerous !== []) {
            return back()->with('success', __(
                'Upload settings saved. Warning: :extensions can be dangerous to allow — only keep them on the list if you understand the risk.',
                ['extensions' => implode(', ', $dangerous)],
            ));
        }

        return back()->with('success', __('Upload settings saved.'));
    }
}
