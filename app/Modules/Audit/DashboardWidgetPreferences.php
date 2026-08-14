<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Models\User;
use App\Modules\Audit\Models\DashboardWidgetPreference;

/**
 * Per-user dashboard layout: which widgets are shown, which column each
 * sits in, and their order within it. Doesn't fit Setting (that's one row
 * per key, install-wide — no per-user dimension), same reasoning as
 * NotificationPreferences, hence the dedicated table.
 */
class DashboardWidgetPreferences
{
    private const DEFAULT_COLUMNS = 2;

    /**
     * Default (column_index, position) for a widget with no stored row
     * yet — a reasonable starting spread, immediately rearrangeable.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const DEFAULT_LAYOUT = [
        'counters' => [0, 0],
        'transfers' => [0, 1],
        'recent' => [0, 2],
        'top_clients_by_storage' => [1, 0],
        'largest_files' => [1, 1],
        'system' => [1, 2],
        'news' => [1, 3],
        'expired_files' => [0, 3],
        'api' => [1, 4],
    ];

    public function isEnabled(User $user, string $widgetKey): bool
    {
        $row = DashboardWidgetPreference::query()
            ->where('user_id', $user->id)
            ->where('widget_key', $widgetKey)
            ->first();

        return $row->enabled ?? true;
    }

    public function columnsFor(User $user): int
    {
        return $user->dashboard_columns ?? self::DEFAULT_COLUMNS;
    }

    /**
     * @param  list<string>  $permittedKeys  Only widget keys the viewer
     *                                       actually holds permission for
     *                                       — never reveal the layout of
     *                                       one they can't see at all.
     * @return array<string, array{enabled: bool, column_index: int, position: int}>
     */
    public function layoutFor(User $user, array $permittedKeys): array
    {
        $rows = DashboardWidgetPreference::query()
            ->where('user_id', $user->id)
            ->whereIn('widget_key', $permittedKeys)
            ->get()
            ->keyBy('widget_key');

        $layout = [];

        foreach ($permittedKeys as $key) {
            $row = $rows->get($key);
            [$defaultColumn, $defaultPosition] = self::DEFAULT_LAYOUT[$key] ?? [0, 0];

            $layout[$key] = [
                'enabled' => $row->enabled ?? true,
                'column_index' => $row->column_index ?? $defaultColumn,
                'position' => $row->position ?? $defaultPosition,
            ];
        }

        return $layout;
    }
}
