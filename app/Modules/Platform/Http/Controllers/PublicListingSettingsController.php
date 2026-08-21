<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The guest-facing public group listing (staff-only to configure): a
 * master switch for the browsable directory, and the base URL segment
 * every public group/file page sits under. See PublicGroupsController
 * for the guest-facing side — a specific public group's page and its
 * downloads work off their own `public` flag regardless of the switch
 * here, which only gates the directory itself.
 */
class PublicListingSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('system/settings/public-listing', [
            'public_listing_enabled' => $this->settings->get(Setting::PublicListingEnabled),
            'public_listing_slug' => $this->settings->get(Setting::PublicListingSlug),
            'public_listing_preview_enabled' => $this->settings->get(Setting::PublicListingPreviewEnabled),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'public_listing_enabled' => ['required', 'boolean'],
            'public_listing_slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'public_listing_preview_enabled' => ['required', 'boolean'],
        ]);

        $this->settings->set(Setting::PublicListingEnabled, $validated['public_listing_enabled']);
        $this->settings->set(Setting::PublicListingSlug, $validated['public_listing_slug']);
        $this->settings->set(Setting::PublicListingPreviewEnabled, $validated['public_listing_preview_enabled']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'public_listing']);

        return back();
    }
}
