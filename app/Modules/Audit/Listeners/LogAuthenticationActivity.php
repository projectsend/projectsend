<?php

declare(strict_types=1);

namespace App\Modules\Audit\Listeners;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

class LogAuthenticationActivity
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->activity->log(Action::Login, $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->activity->log(Action::Logout, $event->user);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
        ];
    }
}
