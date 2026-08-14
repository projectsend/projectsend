<?php

declare(strict_types=1);

namespace App\Modules\Groups;

use App\Modules\Groups\Notifications\GroupMembershipApprovedNotification;
use App\Modules\Notifications\NotificationTypeDefinition;
use App\Modules\Notifications\NotificationTypeRegistry;
use Illuminate\Support\ServiceProvider;

class GroupsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'group.membership_approved',
            label: 'Your group membership request was approved',
            template: 'Your request to join the group ":groupName" has been approved',
            mailNotification: GroupMembershipApprovedNotification::class,
            // Preserves current behavior: this was previously sent
            // unconditionally whenever Setting::EmailNotificationsEnabled
            // was on, with no per-user opt-out — default the preference
            // to "on" so existing users see no change unless they
            // explicitly opt out.
            defaultEmailEnabled: true,
        ));
    }
}
