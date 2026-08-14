<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\AccountContentDeletion;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System-wide privacy settings (staff-only): download IP logging
 * granularity, the account-erasure retention window, how long API request
 * telemetry is kept, and whether to discourage search engines from
 * indexing this installation.
 */
class PrivacySettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly AccountContentDeletion $accountDeletion,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('system/settings/privacy', [
            'download_ip_logging' => $this->settings->get(Setting::DownloadIpLogging),
            'account_erasure_grace_days' => $this->settings->get(Setting::AccountErasureGraceDays),
            'account_erasure_content_action' => $this->settings->get(Setting::AccountErasureContentAction),
            'account_erasure_reassign_to' => $this->settings->get(Setting::AccountErasureReassignTo),
            'reassign_candidates' => $this->accountDeletion->candidates(),
            'api_request_log_retention_days' => $this->settings->get(Setting::ApiRequestLogRetentionDays),
            'discourage_search_indexing' => $this->settings->get(Setting::DiscourageSearchIndexing),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'download_ip_logging' => ['required', Rule::in(['all', 'anonymous_only', 'none'])],
            'account_erasure_grace_days' => ['required', 'integer', 'min:0'],
            'account_erasure_content_action' => ['required', Rule::in(['cascade_delete', 'reassign'])],
            'account_erasure_reassign_to' => [
                'nullable',
                'integer',
                'required_if:account_erasure_content_action,reassign',
                Rule::exists('users', 'id')->where('active', true),
            ],
            'api_request_log_retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'discourage_search_indexing' => ['required', 'boolean'],
        ]);

        $this->settings->set(Setting::DownloadIpLogging, $validated['download_ip_logging']);
        $this->settings->set(Setting::AccountErasureGraceDays, $validated['account_erasure_grace_days']);
        $this->settings->set(Setting::AccountErasureContentAction, $validated['account_erasure_content_action']);
        // Only meaningful for 'reassign'; store 0 otherwise so a later switch
        // back to 'cascade_delete' doesn't leave a stale target lying around.
        $this->settings->set(
            Setting::AccountErasureReassignTo,
            $validated['account_erasure_content_action'] === 'reassign' ? (int) $validated['account_erasure_reassign_to'] : 0,
        );
        $this->settings->set(Setting::ApiRequestLogRetentionDays, $validated['api_request_log_retention_days']);
        $this->settings->set(Setting::DiscourageSearchIndexing, $validated['discourage_search_indexing']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'privacy']);

        return back();
    }
}
