<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

use App\Modules\Platform\Capabilities\Capability;

/**
 * The permission vocabulary — v1's ~45 keys preserved verbatim (brief
 * §6.13) so the v1 importer can carry role assignments across without a
 * mapping table. Enforcement is Laravel Gates registered from this enum;
 * ownership rules ("own" vs "others" files) become policy methods on the
 * models when those modules land.
 */
enum Permission: string
{
    // Files
    case Upload = 'upload';
    case CreateOwnFolders = 'create_own_folders';
    case EditFiles = 'edit_files';
    case EditOthersFiles = 'edit_others_files';
    case DeleteFiles = 'delete_files';
    case DeleteOthersFiles = 'delete_others_files';
    case SetFileExpirationDate = 'set_file_expiration_date';
    case SetFileCategories = 'set_file_categories';
    case UploadPublic = 'upload_public';
    case UploadToPublicFolders = 'upload_to_public_folders';
    case ImportOrphans = 'import_orphans';
    case LimitDownloads = 'limit_downloads';
    // Comments: approve anonymous ones and delete anybody's. Who may
    // *write* a comment is a setting, not a key — see CommentAuthors —
    // but moderating is a staff action, so the cloud edition's single
    // administrator holding it by construction is the right answer
    // rather than a key nobody can reach.
    case ModerateComments = 'moderate_comments';

    // Categories
    case CreateCategories = 'create_categories';
    case EditCategories = 'edit_categories';
    case DeleteCategories = 'delete_categories';

    // Users — staff accounts, the people who administer the installation.
    case CreateUsers = 'create_users';
    case EditUsers = 'edit_users';
    case DeleteUsers = 'delete_users';
    case ManageUsers = 'manage_users';

    // Clients — a different population entirely, with their own screens.
    case CreateClients = 'create_clients';
    case EditClients = 'edit_clients';
    case DeleteClients = 'delete_clients';
    case ManageClients = 'manage_clients';
    case ApproveAccountRequests = 'approve_account_requests';
    case ManageCustomFields = 'manage_custom_fields';

    // Groups
    case CreateGroups = 'create_groups';
    case EditGroups = 'edit_groups';
    case DeleteGroups = 'delete_groups';
    case ApproveGroupsMembershipsRequests = 'approve_groups_memberships_requests';
    case ManageGroups = 'manage_groups';

    // System
    case EditSettings = 'edit_settings';
    case EditEmailTemplates = 'edit_email_templates';
    case ViewActionsLog = 'view_actions_log';
    case ViewStatistics = 'view_statistics';
    case ViewNews = 'view_news';
    case ViewSystemInfo = 'view_system_info';
    case ViewDashboardCounters = 'view_dashboard_counters';
    case ManageUpdates = 'manage_updates';

    // Custom assets
    case CreateAssets = 'create_assets';
    case EditAssets = 'edit_assets';
    case DeleteAssets = 'delete_assets';

    /**
     * English label — also the translation key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Upload files',
            self::CreateOwnFolders => 'Create own folders',
            self::EditFiles => 'Edit own files',
            self::EditOthersFiles => "Edit others' files",
            self::DeleteFiles => 'Delete own files',
            self::DeleteOthersFiles => "Delete others' files",
            self::SetFileExpirationDate => 'Set file expiration dates',
            self::SetFileCategories => 'Assign categories to files',
            self::UploadPublic => 'Upload public files',
            self::UploadToPublicFolders => 'Upload to public folders',
            self::ImportOrphans => 'Import orphan files',
            self::LimitDownloads => 'Limit download counts',
            self::ModerateComments => 'Moderate comments',
            self::CreateCategories => 'Create categories',
            self::EditCategories => 'Edit categories',
            self::DeleteCategories => 'Delete categories',
            self::CreateClients => 'Create clients',
            self::EditClients => 'Edit clients',
            self::DeleteClients => 'Delete clients',
            self::CreateUsers => 'Create system users',
            self::EditUsers => 'Edit system users',
            self::DeleteUsers => 'Delete system users',
            self::ApproveAccountRequests => 'Approve account requests',
            self::ManageUsers => 'Manage system users and roles',
            // Not "manage": this key opens the client list page and its
            // sidebar link, nothing else. Creating, editing and deleting
            // have keys of their own. The stored value stays
            // `manage_clients` — v1's vocabulary is preserved verbatim so
            // the importer needs no mapping table — but the label people
            // read should say what it actually does.
            self::ManageClients => 'View the client list',
            self::ManageCustomFields => 'Manage client custom fields',
            self::CreateGroups => 'Create groups',
            self::EditGroups => 'Edit groups',
            self::DeleteGroups => 'Delete groups',
            self::ApproveGroupsMembershipsRequests => 'Approve group membership requests',
            // Same shape as ManageClients above.
            self::ManageGroups => 'View the group list',
            self::EditSettings => 'Edit system settings',
            self::EditEmailTemplates => 'Edit email templates',
            self::ViewActionsLog => 'View the activity log',
            self::ViewStatistics => 'View statistics',
            self::ViewNews => 'View news',
            self::ViewSystemInfo => 'View system information',
            self::ViewDashboardCounters => 'View dashboard counters',
            self::ManageUpdates => 'Manage updates',
            self::CreateAssets => 'Create custom assets',
            self::EditAssets => 'Edit custom assets',
            self::DeleteAssets => 'Delete custom assets',
        };
    }

    /**
     * The capability this permission depends on, if any.
     *
     * Permissions and capabilities are independent gates: a permission says
     * what a *role* may do, a capability says what this *edition* has at all.
     * The surfaces they guard already apply both — routes/web.php pairs
     * `capability:users.manage` with `can:manage_users`, and the sidebar
     * pairs them again for visibility. Holding `manage_users` on a cloud
     * install therefore means nothing: the feature is absent, not merely
     * hidden.
     *
     * Expressed here so anything that enumerates permissions (the API token
     * form is the first) can ask rather than hardcode its own copy of the
     * pairing, and so a new permission's capability requirement is decided
     * in the same file as the case itself.
     *
     * Null means "available in every edition", which is most of them.
     */
    public function capability(): ?Capability
    {
        return match ($this) {
            self::CreateUsers,
            self::EditUsers,
            self::DeleteUsers,
            self::ManageUsers => Capability::UsersManage,

            self::ManageUpdates => Capability::SystemUpdates,

            self::CreateAssets,
            self::EditAssets,
            self::DeleteAssets => Capability::CustomAssets,

            default => null,
        };
    }

    public function category(): PermissionCategory
    {
        return match ($this) {
            self::Upload,
            self::CreateOwnFolders,
            self::EditFiles,
            self::EditOthersFiles,
            self::DeleteFiles,
            self::DeleteOthersFiles,
            self::SetFileExpirationDate,
            self::SetFileCategories,
            self::UploadPublic,
            self::UploadToPublicFolders,
            self::ImportOrphans,
            self::LimitDownloads,
            self::ModerateComments => PermissionCategory::Files,

            self::CreateCategories,
            self::EditCategories,
            self::DeleteCategories => PermissionCategory::Categories,

            // Staff accounts — the people who administer the installation.
            self::CreateUsers,
            self::EditUsers,
            self::DeleteUsers,
            self::ManageUsers => PermissionCategory::Users,

            // The client population, which is a different thing entirely:
            // custom fields describe clients, and the account-request queue
            // is where self-registered clients wait for approval.
            self::CreateClients,
            self::EditClients,
            self::DeleteClients,
            self::ManageClients,
            self::ManageCustomFields,
            self::ApproveAccountRequests => PermissionCategory::Clients,

            self::CreateGroups,
            self::EditGroups,
            self::DeleteGroups,
            self::ApproveGroupsMembershipsRequests,
            self::ManageGroups => PermissionCategory::Groups,

            self::EditSettings,
            self::EditEmailTemplates,
            self::ViewActionsLog,
            self::ViewStatistics,
            self::ViewNews,
            self::ViewSystemInfo,
            self::ViewDashboardCounters,
            self::ManageUpdates => PermissionCategory::System,

            self::CreateAssets,
            self::EditAssets,
            self::DeleteAssets => PermissionCategory::Assets,
        };
    }
}
