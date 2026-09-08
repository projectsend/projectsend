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
use App\Modules\Platform\Updates\CheckForUpdates;
use Carbon\CarbonImmutable;
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
    /**
     * How long an answer from the release feed is treated as still true.
     * Short enough that "check now" means now, long enough that a room
     * full of administrators cannot spend the server's whole allowance.
     */
    private const CHECK_COOLDOWN_MINUTES = 5;

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
        // No permission beside it, unlike updates: turning the news card
        // off is an ordinary settings change, and edit_settings already
        // gates this whole screen.
        $canConfigureNews = $this->capabilities->has(Capability::NewsConfigure);

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
            // Its own capability, and deliberately not $canManageUpdates:
            // the update block disappears on a managed instance because
            // nobody there can act on it, while this one disappears
            // because the news must keep arriving whether or not the
            // instance's administrator would have chosen it. Null where
            // the choice is not theirs, so the page renders no switch
            // rather than a switch that would do nothing.
            'can_configure_news' => $canConfigureNews,
            'fetch_news' => $canConfigureNews ? $this->settings->get(Setting::FetchNews) : null,
            'last_checked_at' => $canManageUpdates ? $this->lastCheckedAt()?->toIso8601String() : null,
            'check_result' => $request->session()->get('update_check_result'),
        ]);
    }

    /**
     * Ask the release feed now, rather than waiting for tonight's run.
     *
     * Deliberately not gated on Setting::CheckForUpdates: that switches
     * off the unattended daily call, which is not the same as refusing to
     * answer a question somebody has just asked out loud.
     */
    public function checkForUpdates(Request $request, CheckForUpdates $check): RedirectResponse
    {
        $canManageUpdates = $this->capabilities->has(Capability::SystemUpdates)
            && $request->user()?->can('manage_updates') === true;

        abort_unless($canManageUpdates, 403);

        // A second throttle, and not a redundant one. The route's bucket is
        // per user; GitHub's unauthenticated rate limit is per server
        // address, so two administrators each within their own allowance
        // can still exhaust the installation's. This one is the whole
        // installation's, and it costs no new setting — the timestamp it
        // reads is the one every check already writes.
        $lastCheckedAt = $this->lastCheckedAt();

        if ($lastCheckedAt !== null && $lastCheckedAt->gt(now()->subMinutes(self::CHECK_COOLDOWN_MINUTES))) {
            return back()->with('update_check_result', [
                'ok' => true,
                'message' => __('Checked a moment ago, so this is the answer from then.'),
            ]);
        }

        $result = $check->run();

        return back()->with('update_check_result', [
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    private function lastCheckedAt(): ?CarbonImmutable
    {
        $value = $this->settings->get(Setting::LatestVersionCheckedAt);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function update(Request $request): RedirectResponse
    {
        $canManageUpdates = $this->capabilities->has(Capability::SystemUpdates)
            && $request->user()?->can('manage_updates') === true;
        // No permission beside it, unlike updates: turning the news card
        // off is an ordinary settings change, and edit_settings already
        // gates this whole screen.
        $canConfigureNews = $this->capabilities->has(Capability::NewsConfigure);

        $rules = [
            'site_name' => ['required', 'string', 'max:255'],
            // `sometimes` rather than `required`, same convention as
            // check_for_updates below: a caller that does not send it
            // leaves the zone alone instead of being rejected. There is
            // no such thing as clearing it — the empty stored value means
            // "follow APP_TIMEZONE", and only a fresh install has that.
            'timezone' => ['sometimes', 'string', 'timezone', Rule::in($this->timezones->all())],
        ];

        if ($canConfigureNews) {
            $rules['fetch_news'] = ['sometimes', 'boolean'];
        }
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

        // Never read where the choice is not this installation's, so a
        // hand-crafted PATCH cannot switch off the news on a managed
        // instance any more than the absent checkbox could.
        if ($canConfigureNews && array_key_exists('fetch_news', $validated)) {
            $this->settings->set(Setting::FetchNews, $validated['fetch_news']);
        }

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'general']);

        return back();
    }
}
