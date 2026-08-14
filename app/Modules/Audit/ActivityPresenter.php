<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * Turns a log entry into the sentence-ready array the frontend renders,
 * so the dashboard, the activity page, and per-item detail panels all
 * present entries identically.
 */
class ActivityPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(ActivityLog $entry): array
    {
        return [
            'id' => $entry->id,
            'created_at' => $entry->created_at->toIso8601String(),
            'actor_name' => $entry->actor_name,
            'actor_type' => $entry->actor_type,
            // Needed to render an actorless entry: "System" and "Anonymous"
            // are both actor_name null, and only the origin separates them.
            'origin' => $entry->origin->value,
            'template' => $entry->action->template(),
            'replacements' => [
                'subject' => $entry->subject_name
                    ?? ($entry->subject_id !== null ? __('(deleted account)') : ''),
                ...collect($entry->context ?? [])
                    ->filter(fn ($value): bool => is_scalar($value))
                    ->map(fn ($value): string => (string) $value)
                    ->all(),
            ],
        ];
    }
}
