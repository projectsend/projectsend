<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;

/**
 * A page somebody can be sent to after signing in.
 *
 * One case per idea rather than per route: "Files" is the library for
 * staff and the portal's own list for a client, so a single vocabulary
 * serves both kinds of account and a role never has to know which
 * routes its members can reach. Two of them only mean something to staff.
 *
 * requiredPermission() must name what the route's own middleware asks
 * for. It is what keeps somebody from being sent to a page that answers
 * them with a 403, and StartPageTest checks it against the real routes
 * rather than trusting this file to stay in step.
 */
enum StartPage: string
{
    case Dashboard = 'dashboard';
    case Files = 'files';
    case Upload = 'upload';
    case Groups = 'groups';
    case Clients = 'clients';
    case Activity = 'activity';

    /**
     * The choices offered to one kind of account, in menu order.
     *
     * @return list<self>
     */
    public static function optionsFor(UserType $type): array
    {
        return array_values(array_filter(self::cases(), fn (self $page): bool => $page->appliesTo($type)));
    }

    public function appliesTo(UserType $type): bool
    {
        return match ($this) {
            self::Clients, self::Activity => $type === UserType::Staff,
            default => true,
        };
    }

    /**
     * What an account of this type needs to open the page, or null when
     * every account of that type can.
     */
    public function requiredPermission(UserType $type): ?Permission
    {
        $staff = $type === UserType::Staff;

        return match ($this) {
            self::Dashboard => null,
            self::Files => $staff ? Permission::Upload : null,
            self::Upload => Permission::Upload,
            self::Groups => $staff ? Permission::ManageGroups : null,
            self::Clients => Permission::ManageClients,
            self::Activity => Permission::ViewActionsLog,
        };
    }

    public function routeName(UserType $type): string
    {
        $staff = $type === UserType::Staff;

        return match ($this) {
            self::Dashboard => 'dashboard',
            self::Files => $staff ? 'files.index' : 'my-files.index',
            self::Upload => $staff ? 'files.create' : 'my-files.upload.create',
            self::Groups => $staff ? 'groups.index' : 'my-groups.index',
            self::Clients => 'clients.index',
            self::Activity => 'activity.index',
        };
    }

    /**
     * English, and the translation key: the same words the navigation
     * already uses for each page, so they are already translated.
     */
    public function label(UserType $type): string
    {
        $staff = $type === UserType::Staff;

        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Files => $staff ? 'Files' : 'My files',
            self::Upload => 'Upload files',
            self::Groups => $staff ? 'Groups' : 'My groups',
            self::Clients => 'Clients',
            self::Activity => 'Activity log',
        };
    }

    public function isReachableBy(User $user): bool
    {
        if (! $this->appliesTo($user->type)) {
            return false;
        }

        $permission = $this->requiredPermission($user->type);

        return $permission === null || $user->can($permission->value);
    }
}
