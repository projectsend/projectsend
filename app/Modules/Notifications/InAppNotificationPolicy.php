<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\User;

/**
 * A notification belongs to exactly one recipient. Read-path defense in
 * depth alongside every endpoint's own user_id-scoped query (see
 * NotificationsController) — closes the IDOR hole where user A could
 * otherwise guess user B's notification ID via route-model binding.
 */
class InAppNotificationPolicy
{
    public function view(User $user, InAppNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    public function update(User $user, InAppNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}
