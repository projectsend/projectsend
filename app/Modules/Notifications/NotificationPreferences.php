<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\User;

/**
 * Per-user "also email me" choice. Doesn't fit Setting (that's one row
 * per key, install-wide — no per-user dimension), hence the dedicated
 * notification_preferences table.
 */
class NotificationPreferences
{
    public function emailEnabledFor(User $user, NotificationTypeDefinition $type): bool
    {
        $row = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type->key)
            ->first();

        return $row->email_enabled ?? $type->defaultEmailEnabled;
    }
}
