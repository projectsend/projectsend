<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\DashboardWidgetPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saves a staff member's dashboard layout (per-widget enabled/column/
 * position, plus column count) — every account manages their own, same
 * "self-scoped, no extra permission needed" shape as
 * NotificationPreferencesController. Which widgets a viewer can even
 * toggle is enforced entirely by DashboardController's own permission
 * checks on read — this endpoint only ever stores a preference, it never
 * grants visibility a viewer's Gates don't already allow.
 */
class DashboardWidgetPreferencesController extends Controller
{
    /**
     * Every key DashboardController::__invoke() knows how to render —
     * kept here as the single validation allowlist so a stray/typo'd key
     * can't accumulate a dead row.
     */
    private const WIDGET_KEYS = [
        'counters',
        'transfers',
        'top_clients_by_storage',
        'largest_files',
        'recent',
        'system',
        'news',
        'expired_files',
        'api',
    ];

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'columns' => ['required', 'integer', 'between:1,4'],
            'widgets' => ['required', 'array'],
            'widgets.*.widget_key' => ['required', 'string', Rule::in(self::WIDGET_KEYS)],
            'widgets.*.enabled' => ['required', 'boolean'],
            'widgets.*.column_index' => ['required', 'integer', 'between:0,3'],
            'widgets.*.position' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['widgets'] as $widget) {
            DashboardWidgetPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'widget_key' => $widget['widget_key']],
                [
                    'enabled' => $widget['enabled'],
                    'column_index' => $widget['column_index'],
                    'position' => $widget['position'],
                ],
            );
        }

        $user->update(['dashboard_columns' => $validated['columns']]);

        return back();
    }
}
