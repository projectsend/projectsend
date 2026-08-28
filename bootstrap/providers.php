<?php

use App\Modules\Api\ApiServiceProvider;
use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Comments\CommentsServiceProvider;
use App\Modules\Files\FilesServiceProvider;
use App\Modules\Groups\GroupsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Platform\Branding\BrandingServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    ApiServiceProvider::class,
    AuditServiceProvider::class,
    CommentsServiceProvider::class,
    FilesServiceProvider::class,
    GroupsServiceProvider::class,
    IdentityServiceProvider::class,
    NotificationsServiceProvider::class,
    PlatformServiceProvider::class,
    BrandingServiceProvider::class,
    AppServiceProvider::class,
];
