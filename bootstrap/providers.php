<?php

use App\Modules\Api\ApiServiceProvider;
use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Clients\ClientsServiceProvider;
use App\Modules\Comments\CommentsServiceProvider;
use App\Modules\Files\FilesServiceProvider;
use App\Modules\Groups\GroupsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Mcp\McpServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Platform\Branding\BrandingServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    ApiServiceProvider::class,
    AuditServiceProvider::class,
    ClientsServiceProvider::class,
    CommentsServiceProvider::class,
    FilesServiceProvider::class,
    GroupsServiceProvider::class,
    IdentityServiceProvider::class,
    McpServiceProvider::class,
    NotificationsServiceProvider::class,
    PlatformServiceProvider::class,
    BrandingServiceProvider::class,
    AppServiceProvider::class,

    // An installation's own providers, if it has any. No release ships
    // this file, so an update leaves it alone where it overwrites this one
    // — the update-safe place to register a theme or anything else the
    // registries accept. Spread last so a local provider boots after core
    // and can replace what core registered, and kept in this file rather
    // than in withProviders(), which Laravel merges *before* it. A listed
    // class that no longer exists is skipped by Laravel, not fatal.
    ...(is_file(__DIR__.'/providers.local.php') ? require __DIR__.'/providers.local.php' : []),
];
