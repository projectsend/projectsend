<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Controllers;

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
 * Whether AI assistants may connect to this site at all.
 *
 * Off closes /mcp, its authorization server and its discovery
 * documents, and hides the AI connections page. Nothing is deleted:
 * connections already made stop working, and work again if the switch
 * is turned back on, the way a capability change never deletes a row.
 */
class AiAssistantSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('system/settings/ai-assistants', [
            'ai_connector_enabled' => $this->settings->get(Setting::AiConnectorEnabled),
            'endpoint' => rtrim((string) config('app.url'), '/').'/mcp',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_connector_enabled' => ['required', 'boolean'],
        ]);

        $this->settings->set(Setting::AiConnectorEnabled, (bool) $validated['ai_connector_enabled']);

        $this->activity->log(Action::SettingsUpdated, context: [
            'section' => 'ai_assistants',
            'ai_connector_enabled' => (bool) $validated['ai_connector_enabled'],
        ]);

        return back();
    }
}
