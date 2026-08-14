<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

/**
 * How the permission vocabulary is grouped for the two screens that show
 * all of it at once: the roles matrix and the API token form.
 *
 * Presentation only — nothing is enforced per category. `cases()` order is
 * the order both screens render in.
 */
enum PermissionCategory: string
{
    case Files = 'files';
    case Categories = 'categories';
    case Users = 'users';
    case Clients = 'clients';
    case Groups = 'groups';
    case System = 'system';
    case Assets = 'assets';

    /**
     * English label — also the translation key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Files => 'Files',
            self::Categories => 'Categories',
            self::Users => 'Users',
            self::Clients => 'Clients',
            self::Groups => 'Groups',
            self::System => 'System',
            self::Assets => 'Custom assets',
        };
    }
}
