<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Console\CreateAdminCommand;
use App\Modules\Identity\Console\EnsureSystemRolesCommand;
use App\Modules\Identity\Console\EraseAccountCommand;
use App\Modules\Identity\Console\PurgeErasuresCommand;
use App\Modules\Identity\Ldap\LdapDirectory;
use App\Modules\Identity\Ldap\LdapRecordDirectory;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\Social\SocialGateway;
use App\Modules\Identity\Social\SocialiteGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionChecker::class);

        // Bound to the interface, not resolved concretely, so a test can
        // swap in a fake directory and exercise the whole login flow —
        // which account types may authenticate, provisioning, the
        // two-factor hand-off — without a directory server anywhere.
        $this->app->bind(LdapDirectory::class, LdapRecordDirectory::class);

        // Same reasoning for identity providers: a fake gateway lets the
        // resolution rules — which are the whole security value of the
        // feature — be exercised without an OAuth server.
        $this->app->bind(SocialGateway::class, SocialiteGateway::class);
    }

    public function boot(): void
    {
        // One Gate ability per permission key: `$user->can('edit_settings')`,
        // `can:view_actions_log` route middleware, @can in views.
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user): bool => $this->app->make(PermissionChecker::class)->allows($user, $permission),
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAdminCommand::class,
                EnsureSystemRolesCommand::class,
                EraseAccountCommand::class,
                PurgeErasuresCommand::class,
            ]);
        }
    }
}
