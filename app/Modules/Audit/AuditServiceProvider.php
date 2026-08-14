<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Listeners\LogAuthenticationActivity;
use App\Modules\Audit\Listeners\LogCustomAssetActivity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActivityLogger::class);
    }

    public function boot(): void
    {
        Event::subscribe(LogAuthenticationActivity::class);
        Event::subscribe(LogCustomAssetActivity::class);
    }
}
