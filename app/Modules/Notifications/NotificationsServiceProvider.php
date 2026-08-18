<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the registry singleton only — see NotificationTypeRegistry's
 * docblock for why this provider registers zero types itself.
 */
class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationTypeRegistry::class);
        $this->app->singleton(Notifier::class);
        $this->app->singleton(NotificationPreferences::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\PurgeNotificationsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        Gate::policy(InAppNotification::class, InAppNotificationPolicy::class);
    }
}
