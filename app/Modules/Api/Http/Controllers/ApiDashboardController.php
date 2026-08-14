<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\ApiUsage;
use App\Modules\Api\ApiUsageScope;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the API has been doing: token inventory, request volume, and the
 * domain actions taken through it.
 *
 * Two sources, on purpose. Volume, latency and error rates come from
 * `api_request_logs`, which records every call; "what did this token
 * change" comes from the activity log, which records domain events and
 * links back to the full history. Neither could answer the other's
 * question.
 */
class ApiDashboardController extends Controller
{
    public function __construct(
        private readonly ApiUsage $usage,
        private readonly ApiUsageScope $scope,
        private readonly Settings $settings,
    ) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);

        // The request may ask for the install-wide view; the scope decides
        // whether it gets one. A viewer without the permission silently
        // falls back to their own tokens rather than being refused — the
        // page is theirs either way, only its reach differs.
        $installWide = $this->scope->resolve($viewer, $request->boolean('all'));

        return Inertia::render('api/dashboard', [
            'summary' => $this->usage->summary($viewer, $installWide),
            'daily' => $this->usage->daily($viewer, $installWide),
            'tokens' => $this->usage->tokenUsage($viewer, $installWide),
            'recent_actions' => $this->usage->recentActions($viewer, $installWide),
            'top_endpoints' => $this->usage->topEndpoints($viewer, $installWide),
            'scope' => [
                'install_wide' => $installWide,
                'can_view_install_wide' => $this->scope->mayViewInstallWide($viewer),
            ],
            'retention_days' => (int) $this->settings->get(Setting::ApiRequestLogRetentionDays),
        ]);
    }
}
