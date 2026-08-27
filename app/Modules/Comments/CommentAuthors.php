<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Modules\Identity\UserType;

/**
 * Who may write a comment (Setting::CommentsAuthors).
 *
 * This is a setting rather than a permission on purpose, and one of the
 * two reasons has since expired. It used to be that roles were editable
 * only in the community edition — the cloud edition gated the whole roles
 * screen behind Capability::UsersManage — so a permission key would have
 * been unconfigurable for half our installs. That stopped being true in
 * 2.2.0, when users.manage opened on both editions.
 *
 * The reason that carries it now is the one a permission structurally
 * cannot express: `Everyone` includes anonymous visitors, who have no
 * account and therefore no role to hold a key. That was always the
 * stronger half; it is now the whole of it.
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
