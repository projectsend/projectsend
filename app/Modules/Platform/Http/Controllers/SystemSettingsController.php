<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System-wide settings (v1's "options"), staff-only. Fine-grained
 * permissions arrive with the Phase 1 role system; until then any staff
 * account may edit.
 */
class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly CapabilityRegistry $capabilities,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function edit(Request $request): Response
    {
        $canManageUpdates = $this->capabilities->has(Capability::SystemUpdates)
            && $request->user()?->can('manage_updates') === true;

        return Inertia::render('system/settings/general', [
            'site_name' => $this->settings->get(Setting::SiteName),
            // Resolved rather than raw: the stored value is empty on a
            // fresh install ("whatever APP_TIMEZONE says"), and a picker
            // showing nothing selected would invite an administrator to
            // believe no zone is in effect.
            'timezone' => $this->timezones->default(),
            'timezones' => $this->timezones->options(),
            // The administrator's own zone, when they have chosen one —
            // null otherwise. Sent so the page can warn that this setting
            // will not change what *they* see: their preference outranks
            // it, and without being told they will change the setting,
            // watch nothing move, and conclude it is broken.
            'viewer_timezone' => $request->user()?->timezone,
            'can_manage_updates' => $canManageUpdates,
            'check_for_updates' => $canManageUpdates ? $this->settings->get(Setting::CheckForUpdates) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $canManageUpdates = $this->capabilities->has(Capability::SystemUpdates)
            && $request->user()?->can('manage_updates') === true;

        $rules = [
            'site_name' => ['required', 'string', 'max:255'],
            // `sometimes` rather than `required`, same convention as
            // check_for_updates below: a caller that does not send it
            // leaves the zone alone instead of being rejected. There is
            // no such thing as clearing it — the empty stored value means
            // "follow APP_TIMEZONE", and only a fresh install has that.
            'timezone' => ['sometimes', 'string', 'timezone', Rule::in($this->timezones->all())],
        ];
        if ($canManageUpdates) {
            // Omitting the field (any caller not sending it, not just this
            // page's own form) leaves the current value alone rather than
            // erroring — same convention as FoldersController::update()'s
            // optional public/slug fields.
            $rules['check_for_updates'] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);

        $this->settings->set(Setting::SiteName, $validated['site_name']);

        if (array_key_exists('timezone', $validated)) {
            $this->settings->set(Setting::Timezone, $validated['timezone']);
        }

        // Simply never read from the request when the capability/permission
        // is absent — a hand-crafted PATCH can't smuggle this on for a
        // cloud install or a staff member without manage_updates either.
        if ($canManageUpdates && array_key_exists('check_for_updates', $validated)) {
            $this->settings->set(Setting::CheckForUpdates, $validated['check_for_updates']);
        }

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'general']);

        return back();
    }
}
