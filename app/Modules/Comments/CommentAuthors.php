<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Modules\Identity\UserType;

/**
 * Who may write a comment (Setting::CommentsAuthors).
 *
 * This is a setting rather than a permission on purpose. Roles are only
 * editable in the community edition — the cloud edition gates the whole
 * roles screen behind Capability::UsersManage — so a permission key would
 * be unconfigurable for half our installs. It also expresses something a
 * permission structurally cannot: `Everyone` includes anonymous visitors,
 * who have no account and therefore no role to hold a key.
 */
enum CommentAuthors: string
{
    case Staff = 'staff';
    case Clients = 'clients';
    case StaffAndClients = 'staff_and_clients';
    case Everyone = 'everyone';

    /**
     * @param  UserType|null  $type  Null means an anonymous visitor.
     */
    public function allows(?UserType $type): bool
    {
        return match ($this) {
            self::Staff => $type === UserType::Staff,
            self::Clients => $type === UserType::Client,
            self::StaffAndClients => $type !== null,
            self::Everyone => true,
        };
    }

    /**
     * English label — also the translation key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff only',
            self::Clients => 'Clients only',
            self::StaffAndClients => 'Staff and clients',
            self::Everyone => 'Anyone, including visitors who are not logged in',
        };
    }
}
