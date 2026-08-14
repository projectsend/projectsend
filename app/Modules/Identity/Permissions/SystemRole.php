<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

/**
 * The built-in roles the system knows how to define. Fresh installs seed
 * System Administrator, Account Manager, Client Manager, and Client;
 * System Administrator holds every permission by construction — enforced
 * in code (PermissionChecker), not via pivot rows, so newly added
 * permissions can never be silently missing from the admin role.
 *
 * Uploader is a **legacy** role (v1's level 7): it is NOT seeded on new
 * installs — an unscoped file-only staffer doesn't fit the v2 model, where
 * limited staff are client-scoped (Client Manager). It is retained only so
 * the v1 → v2 migration tool can recreate it as "Uploader (Legacy)" for
 * imported installations while recommending each such user be converted to
 * Client Manager instead.
 */
enum SystemRole: string
{
    case SystemAdministrator = 'System Administrator';
    case AccountManager = 'Account Manager';
    case ClientManager = 'Client Manager';
    case Uploader = 'Uploader';
    case Client = 'Client';

    public function isAdministrator(): bool
    {
        return $this === self::SystemAdministrator;
    }

    /**
     * A client-scoped role sees only the files & folders belonging to the
     * clients assigned to its members, plus their own uploads.
     */
    public function isClientScoped(): bool
    {
        return $this === self::ClientManager;
    }

    /**
     * A legacy role kept only for v1 imports — never seeded on a fresh
     * install (the migration tool recreates it as "Uploader (Legacy)").
     */
    public function isLegacy(): bool
    {
        return $this === self::Uploader;
    }

    /**
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SystemAdministrator => Permission::cases(),

            self::AccountManager => [
                Permission::Upload,
                Permission::EditFiles,
                Permission::EditOthersFiles,
                Permission::DeleteFiles,
                Permission::DeleteOthersFiles,
                Permission::SetFileExpirationDate,
                Permission::UploadPublic,
                Permission::ImportOrphans,
                Permission::ModerateComments,
                Permission::CreateCategories,
                Permission::EditCategories,
                Permission::DeleteCategories,
                Permission::CreateClients,
                Permission::EditClients,
                Permission::DeleteClients,
                Permission::ManageCustomFields,
                Permission::ApproveAccountRequests,
                Permission::CreateGroups,
                Permission::EditGroups,
                Permission::DeleteGroups,
                Permission::ApproveGroupsMembershipsRequests,
                Permission::ViewActionsLog,
                Permission::ViewStatistics,
                Permission::ViewNews,
            ],

            self::Uploader => [
                Permission::Upload,
                Permission::EditFiles,
                Permission::DeleteFiles,
                Permission::SetFileExpirationDate,
                Permission::UploadPublic,
                Permission::ImportOrphans,
                Permission::CreateCategories,
                Permission::EditCategories,
                Permission::DeleteCategories,
                Permission::ViewActionsLog,
                Permission::ViewStatistics,
                Permission::ViewNews,
            ],

            // A file-focused staff role (upload, edit/delete own files,
            // categories, own folders) that is client_scoped: members only
            // see the library content of the clients assigned to them, plus
            // their own uploads. This is the v2 successor to the legacy
            // Uploader role.
            self::ClientManager => [
                Permission::Upload,
                Permission::EditFiles,
                Permission::DeleteFiles,
                Permission::SetFileExpirationDate,
                Permission::UploadPublic,
                Permission::CreateCategories,
                Permission::EditCategories,
                Permission::DeleteCategories,
                Permission::CreateOwnFolders,
                Permission::ViewActionsLog,
                Permission::ViewStatistics,
                Permission::ViewNews,
            ],

            // Clients can both send and organize by default — upload and
            // create_own_folders travel together (see FolderPolicy/
            // MyFoldersController: creating a folder you can never put
            // anything in makes no sense on its own). An admin can still
            // narrow this per role via the Roles UI.
            self::Client => [
                Permission::Upload,
                Permission::CreateOwnFolders,
            ],
        };
    }
}
