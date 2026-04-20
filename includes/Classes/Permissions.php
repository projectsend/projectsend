<?php
namespace ProjectSend\Classes;

class Permissions {
    private $permissions;
    private $user;

    public function __construct($user_id = null)
    {
        $this->setDefaultPermissions();

        if (!empty($user_id)) {
            $this->user = new \ProjectSend\Classes\Users($user_id);
            $this->setPermissionsForRole($this->user->role_id);
            $this->setPermissionsFromSettings();
        }
    }

    /**
     * Get core permissions definition for migration and fallback purposes only
     * This should NOT be used for permission checking in production
     * @return array
     */
    public static function getCorePermissionsDefinition()
    {
        return [
            // File Management
            'upload' => [
                'category' => 'files',
                'label' => __('Upload files', 'cftp_admin'),
                'description' => __('Allow user to upload new files', 'cftp_admin')
            ],
            'edit_files' => [
                'category' => 'files',
                'label' => __('Edit own files', 'cftp_admin'),
                'description' => __('Allow user to edit their own uploaded files', 'cftp_admin')
            ],
            'edit_others_files' => [
                'category' => 'files',
                'label' => __('Edit others files', 'cftp_admin'),
                'description' => __('Allow user to edit files uploaded by other users', 'cftp_admin')
            ],
            'delete_files' => [
                'category' => 'files',
                'label' => __('Delete own files', 'cftp_admin'),
                'description' => __('Allow user to delete their own uploaded files', 'cftp_admin')
            ],
            'delete_others_files' => [
                'category' => 'files',
                'label' => __('Delete others files', 'cftp_admin'),
                'description' => __('Allow user to delete files uploaded by other users', 'cftp_admin')
            ],
            'set_file_expiration_date' => [
                'category' => 'files',
                'label' => __('Set file expiration', 'cftp_admin'),
                'description' => __('Allow user to set expiration dates on files', 'cftp_admin')
            ],
            'set_file_categories' => [
                'category' => 'files',
                'label' => __('Assign categories', 'cftp_admin'),
                'description' => __('Allow user to assign categories to files', 'cftp_admin')
            ],
            'upload_public' => [
                'category' => 'files',
                'label' => __('Upload public files', 'cftp_admin'),
                'description' => __('Allow user to mark uploaded files as public', 'cftp_admin')
            ],
            'upload_to_public_folders' => [
                'category' => 'files',
                'label' => __('Upload to public folders', 'cftp_admin'),
                'description' => __('Allow user to upload files to public folders', 'cftp_admin')
            ],
            'import_orphans' => [
                'category' => 'files',
                'label' => __('Import orphan files', 'cftp_admin'),
                'description' => __('Allow user to import orphan files from upload directory', 'cftp_admin')
            ],
            'upload_storage_select' => [
                'category' => 'files',
                'label' => __('Select upload storage', 'cftp_admin'),
                'description' => __('Allow user to select storage destination during file upload', 'cftp_admin')
            ],
            'limit_downloads' => [
                'category' => 'files',
                'label' => __('Set download limits', 'cftp_admin'),
                'description' => __('Allow user to set download limits on files', 'cftp_admin')
            ],

            // Category Management
            'create_categories' => [
                'category' => 'categories',
                'label' => __('Create categories', 'cftp_admin'),
                'description' => __('Allow user to create new file categories', 'cftp_admin')
            ],
            'edit_categories' => [
                'category' => 'categories',
                'label' => __('Edit categories', 'cftp_admin'),
                'description' => __('Allow user to edit existing categories', 'cftp_admin')
            ],
            'delete_categories' => [
                'category' => 'categories',
                'label' => __('Delete categories', 'cftp_admin'),
                'description' => __('Allow user to delete categories', 'cftp_admin')
            ],

            // User Management
            'create_clients' => [
                'category' => 'users',
                'label' => __('Create clients', 'cftp_admin'),
                'description' => __('Allow user to create new client accounts', 'cftp_admin')
            ],
            'edit_clients' => [
                'category' => 'users',
                'label' => __('Edit clients', 'cftp_admin'),
                'description' => __('Allow user to edit client accounts', 'cftp_admin')
            ],
            'delete_clients' => [
                'category' => 'users',
                'label' => __('Delete clients', 'cftp_admin'),
                'description' => __('Allow user to delete client accounts', 'cftp_admin')
            ],
            'create_users' => [
                'category' => 'users',
                'label' => __('Create system users', 'cftp_admin'),
                'description' => __('Allow user to create new system user accounts', 'cftp_admin')
            ],
            'edit_users' => [
                'category' => 'users',
                'label' => __('Edit system users', 'cftp_admin'),
                'description' => __('Allow user to edit system user accounts', 'cftp_admin')
            ],
            'delete_users' => [
                'category' => 'users',
                'label' => __('Delete system users', 'cftp_admin'),
                'description' => __('Allow user to delete system user accounts', 'cftp_admin')
            ],
            'edit_self_account' => [
                'category' => 'users',
                'label' => __('Edit own account', 'cftp_admin'),
                'description' => __('Allow user to edit their own account details', 'cftp_admin')
            ],
            'approve_account_requests' => [
                'category' => 'users',
                'label' => __('Approve accounts', 'cftp_admin'),
                'description' => __('Allow user to approve new account registration requests', 'cftp_admin')
            ],
            'manage_users' => [
                'category' => 'users',
                'label' => __('Manage system users', 'cftp_admin'),
                'description' => __('Allow user to manage system user accounts', 'cftp_admin')
            ],
            'manage_clients' => [
                'category' => 'users',
                'label' => __('Manage clients', 'cftp_admin'),
                'description' => __('Allow user to manage client accounts', 'cftp_admin')
            ],

            // Group Management
            'create_groups' => [
                'category' => 'groups',
                'label' => __('Create groups', 'cftp_admin'),
                'description' => __('Allow user to create new groups', 'cftp_admin')
            ],
            'edit_groups' => [
                'category' => 'groups',
                'label' => __('Edit groups', 'cftp_admin'),
                'description' => __('Allow user to edit existing groups', 'cftp_admin')
            ],
            'delete_groups' => [
                'category' => 'groups',
                'label' => __('Delete groups', 'cftp_admin'),
                'description' => __('Allow user to delete groups', 'cftp_admin')
            ],
            'approve_groups_memberships_requests' => [
                'category' => 'groups',
                'label' => __('Approve memberships', 'cftp_admin'),
                'description' => __('Allow user to approve group membership requests', 'cftp_admin')
            ],
            'manage_groups' => [
                'category' => 'groups',
                'label' => __('Manage groups', 'cftp_admin'),
                'description' => __('Allow user to manage client groups', 'cftp_admin')
            ],

            // System Administration
            'edit_settings' => [
                'category' => 'system',
                'label' => __('Edit settings', 'cftp_admin'),
                'description' => __('Allow user to modify system settings', 'cftp_admin')
            ],
            'edit_email_templates' => [
                'category' => 'system',
                'label' => __('Edit email templates', 'cftp_admin'),
                'description' => __('Allow user to edit email notification templates', 'cftp_admin')
            ],
            'change_template' => [
                'category' => 'system',
                'label' => __('Change template', 'cftp_admin'),
                'description' => __('Allow user to change the client interface template', 'cftp_admin')
            ],
            'view_actions_log' => [
                'category' => 'system',
                'label' => __('View activity log', 'cftp_admin'),
                'description' => __('Allow user to view system activity log', 'cftp_admin')
            ],
            'view_statistics' => [
                'category' => 'system',
                'label' => __('View statistics', 'cftp_admin'),
                'description' => __('Allow user to view system statistics', 'cftp_admin')
            ],
            'view_news' => [
                'category' => 'system',
                'label' => __('View news', 'cftp_admin'),
                'description' => __('Allow user to view system news and updates', 'cftp_admin')
            ],
            'view_system_info' => [
                'category' => 'system',
                'label' => __('View system info', 'cftp_admin'),
                'description' => __('Allow user to view system information', 'cftp_admin')
            ],
            'view_dashboard_counters' => [
                'category' => 'system',
                'label' => __('View dashboard counters', 'cftp_admin'),
                'description' => __('Allow user to view dashboard statistics counters', 'cftp_admin')
            ],
            'test_email' => [
                'category' => 'system',
                'label' => __('Test email', 'cftp_admin'),
                'description' => __('Allow user to send test emails', 'cftp_admin')
            ],
            'unblock_ip' => [
                'category' => 'system',
                'label' => __('Unblock IP', 'cftp_admin'),
                'description' => __('Allow user to unblock IP addresses', 'cftp_admin')
            ],
            'manage_updates' => [
                'category' => 'system',
                'label' => __('Manage system updates', 'cftp_admin'),
                'description' => __('Allow user to download and install system updates', 'cftp_admin')
            ],

            // Asset Management
            'create_assets' => [
                'category' => 'assets',
                'label' => __('Create assets', 'cftp_admin'),
                'description' => __('Allow user to create custom CSS/JS assets', 'cftp_admin')
            ],
            'edit_assets' => [
                'category' => 'assets',
                'label' => __('Edit assets', 'cftp_admin'),
                'description' => __('Allow user to edit custom CSS/JS assets', 'cftp_admin')
            ],
            'delete_assets' => [
                'category' => 'assets',
                'label' => __('Delete assets', 'cftp_admin'),
                'description' => __('Allow user to delete custom CSS/JS assets', 'cftp_admin')
            ],
        ];
    }

    /**
     * Get all permissions from database
     * @return array
     */
    public static function getAllPermissionsFromDatabase($exclude_always_granted = true)
    {
        if (!table_exists(TABLE_PERMISSIONS)) {
            $permissions = self::getCorePermissionsDefinition(); // Fallback during initial setup

            // Exclude always-granted permissions if requested
            if ($exclude_always_granted) {
                unset($permissions['edit_self_account']);
                unset($permissions['edit_files']);
            }

            return $permissions;
        }

        global $dbh;

        $sql = "SELECT permission_key, name, description, category FROM " . TABLE_PERMISSIONS . "
                WHERE active = 1";

        // Exclude permissions that are always granted to all users
        // These don't need to be managed through the permissions interface
        if ($exclude_always_granted) {
            $always_granted = ['edit_self_account', 'edit_files'];
            $exclude_list = "'" . implode("','", $always_granted) . "'";
            $sql .= " AND permission_key NOT IN ($exclude_list)";
        }

        $sql .= " ORDER BY category, name";
        $statement = $dbh->prepare($sql);
        $statement->execute();

        $permissions = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $permissions[$row['permission_key']] = [
                'label' => $row['name'],
                'description' => $row['description'],
                'category' => $row['category']
            ];
        }

        return $permissions;
    }

    /**
     * Get permission categories from database
     * @return array
     */
    public static function getPermissionCategories()
    {
        if (!table_exists(TABLE_PERMISSIONS)) {
            return [
                'files' => __('File Management', 'cftp_admin'),
                'categories' => __('Category Management', 'cftp_admin'),
                'users' => __('User Management', 'cftp_admin'),
                'groups' => __('Group Management', 'cftp_admin'),
                'system' => __('System Administration', 'cftp_admin'),
                'dashboard' => __('Dashboard', 'cftp_admin'),
                'assets' => __('Asset Management', 'cftp_admin'),
            ];
        }

        global $dbh;

        $sql = "SELECT DISTINCT category FROM " . TABLE_PERMISSIONS . " WHERE active = 1 ORDER BY category";
        $statement = $dbh->prepare($sql);
        $statement->execute();

        $categories = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $category = $row['category'];
            // Provide nice display names
            switch ($category) {
                case 'files':
                    $categories[$category] = __('File Management', 'cftp_admin');
                    break;
                case 'categories':
                    $categories[$category] = __('Category Management', 'cftp_admin');
                    break;
                case 'users':
                    $categories[$category] = __('User Management', 'cftp_admin');
                    break;
                case 'groups':
                    $categories[$category] = __('Group Management', 'cftp_admin');
                    break;
                case 'system':
                    $categories[$category] = __('System Administration', 'cftp_admin');
                    break;
                case 'dashboard':
                    $categories[$category] = __('Dashboard', 'cftp_admin');
                    break;
                case 'assets':
                    $categories[$category] = __('Asset Management', 'cftp_admin');
                    break;
                default:
                    $categories[$category] = ucfirst($category);
                    break;
            }
        }

        return $categories;
    }

    /**
     * Get permissions grouped by category from database
     * @return array
     */
    public static function getPermissionsGroupedByCategory()
    {
        $permissions = self::getAllPermissionsFromDatabase();
        $grouped = [];

        foreach ($permissions as $permission_key => $permission_data) {
            $category = $permission_data['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$permission_key] = $permission_data;
        }

        return $grouped;
    }

    /**
     * Get all permissions for a specific role from database
     * @param int $role
     * @return array
     */
    public static function getPermissionsForRole($role)
    {
        if (!table_exists(TABLE_ROLE_PERMISSIONS)) {
            return [];
        }

        global $dbh;

        $sql = "SELECT permission FROM " . TABLE_ROLE_PERMISSIONS . " rp
                WHERE rp.role_id = :role_id AND rp.granted = 1";
        $statement = $dbh->prepare($sql);
        $statement->execute(['role_id' => $role]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get permission metadata from database
     * @param string $permission
     * @return array|null
     */
    public static function getPermissionData($permission)
    {
        if (!table_exists(TABLE_PERMISSIONS)) {
            $legacy = self::getCorePermissionsDefinition();
            return isset($legacy[$permission]) ? $legacy[$permission] : null;
        }

        global $dbh;

        $sql = "SELECT permission_key, name, description, category FROM " . TABLE_PERMISSIONS . "
                WHERE permission_key = :permission AND active = 1";
        $statement = $dbh->prepare($sql);
        $statement->execute(['permission' => $permission]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'label' => $row['name'],
                'description' => $row['description'],
                'category' => $row['category']
            ];
        }

        return null;
    }

    /**
     * Check if a permission exists in database
     * @param string $permission
     * @return bool
     */
    public static function permissionExists($permission)
    {
        if (!table_exists(TABLE_PERMISSIONS)) {
            $legacy = self::getCorePermissionsDefinition();
            return isset($legacy[$permission]);
        }

        global $dbh;

        $sql = "SELECT id FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission AND active = 1";
        $statement = $dbh->prepare($sql);
        $statement->execute(['permission' => $permission]);

        return (bool)$statement->fetch();
    }

    /**
     * Ensure all core permissions exist in database
     * Auto-creates missing permissions from the static definition
     */
    public static function ensureCorePermissionsExist()
    {
        if (!table_exists(TABLE_PERMISSIONS) || get_option('auto_create_missing_permissions') != '1') {
            return;
        }

        global $dbh;

        $core_permissions = self::getCorePermissionsDefinition();

        foreach ($core_permissions as $permission_key => $permission_data) {
            // Check if permission exists
            $check_sql = "SELECT id FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission_key";
            $statement = $dbh->prepare($check_sql);
            $statement->execute(['permission_key' => $permission_key]);

            if (!$statement->fetch()) {
                // Create missing permission
                $insert_sql = "INSERT INTO " . TABLE_PERMISSIONS . "
                    (permission_key, name, description, category, is_system_permission, active)
                    VALUES (:permission_key, :name, :description, :category, 1, 1)";

                $statement = $dbh->prepare($insert_sql);
                $statement->execute([
                    'permission_key' => $permission_key,
                    'name' => $permission_data['label'] ?? $permission_key,
                    'description' => $permission_data['description'] ?? '',
                    'category' => $permission_data['category'] ?? 'general'
                ]);

                error_log("ProjectSend: Auto-created missing permission: $permission_key");
            }
        }
    }

    private function setDefaultPermissions()
    {
        // Get all permissions from database (including always-granted ones for internal use)
        $permissions = self::getAllPermissionsFromDatabase(false);
        foreach ($permissions as $permission => $data) {
            $this->permissions[$permission] = false;
        }
    }

    private function setPermissionsFromSettings()
    {
        if (empty($this->user)) {
            return;
        }

        // Client permissions are now handled through the role-based system
        // The old option-based system has been migrated to role permissions
        if ($this->user->isClient()) {
            // Check upload_public permission from client role instead of using old settings-based approach
            $client_role = new \ProjectSend\Classes\Roles(\ProjectSend\Classes\Roles::getClientRoleId());
            $this->permissions['upload_public'] = $client_role->hasPermission('upload_public');
        }
    }

    public function can($permission)
    {
        // These permissions are fundamental user rights - always granted
        // - edit_self_account: Everyone should be able to edit their own account
        // - edit_files: Everyone should be able to edit their own uploaded files
        $always_available = ['edit_self_account', 'edit_files'];
        if (in_array($permission, $always_available)) {
            return true;
        }

        // Return false if permission doesn't exist
        if (!isset($this->permissions[$permission])) {
            return false;
        }

        return $this->permissions[$permission];
    }

    public function set($permission, $value = false)
    {
        if (!array_key_exists($permission, $this->permissions)) {
            return;
        }

        $this->permissions[$permission] = (bool)$value;
    }

    public function setPermissionsForRole($role_id = null)
    {
        if (empty($role_id)) {
            return;
        }

        $role_id = (int)$role_id;

        // Check if this is the System Administrator role
        try {
            $role = new \ProjectSend\Classes\Roles($role_id);
            if ($role->exists() && $role->name === 'System Administrator') {
                $this->setAllPermissions();
                return;
            }
        } catch (Exception $e) {
            // If role loading fails, continue with database lookup
        }

        // For all other roles, load from database
        if ($this->setPermissionsFromDatabase($role_id)) {
            return;
        }

        // Fallback: if no permissions found in database, grant basic permissions for non-clients
        if ($role_id > 1) { // Not a client role
            $this->setDefaultUserPermissions();
        }
    }

    /**
     * Set ALL permissions to true (used for System Admin)
     */
    private function setAllPermissions()
    {
        $all_permissions = self::getAllPermissionsFromDatabase(false);
        foreach ($all_permissions as $permission => $data) {
            $this->permissions[$permission] = true;
        }
    }

    /**
     * Load permissions from database
     * @param int $role
     * @return bool
     */
    private function setPermissionsFromDatabase($role)
    {
        if (!table_exists(TABLE_ROLE_PERMISSIONS)) {
            return false;
        }

        try {
            global $dbh;

            $sql = "SELECT permission FROM " . TABLE_ROLE_PERMISSIONS . "
                    WHERE role_id = :role_id AND granted = 1";
            $statement = $dbh->prepare($sql);
            $statement->execute(['role_id' => $role]);

            $db_permissions = $statement->fetchAll(\PDO::FETCH_COLUMN);

            // Set all permissions to false first
            $all_permissions = self::getAllPermissionsFromDatabase(false);
            foreach ($all_permissions as $permission => $data) {
                $this->permissions[$permission] = false;
            }

            // Enable permissions found in database
            foreach ($db_permissions as $permission) {
                if (isset($this->permissions[$permission])) {
                    $this->permissions[$permission] = true;
                }
            }

            return true;
        } catch (Exception $e) {
            // Database error, fall back to hardcoded permissions
            error_log("ProjectSend: Error loading permissions from database: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Fallback to hardcoded role permissions
     * @param int $role
     */
    private function setPermissionsFromHardcodedRoles($role)
    {
        if (!in_array($role, ['Client', 'Uploader', 'Account Manager', 'System Administrator'])) {
            return;
        }

        // This method should not be used in production with database-driven permissions
        // Keeping minimal fallback for extreme cases
        error_log("ProjectSend Warning: Using hardcoded role permissions fallback for role $role");

        // Basic permissions for backward compatibility
        $basic_permissions = [
            'edit_self_account' => true // Everyone can edit their own account
        ];

        foreach ($basic_permissions as $permission => $value) {
            if (isset($this->permissions[$permission])) {
                $this->permissions[$permission] = $value;
            }
        }
    }

    /**
     * Get all current permissions for this instance
     * @return array
     */
    public function getAllPermissions()
    {
        return $this->permissions;
    }

    /**
     * Get only the permissions that are enabled for this instance
     * @return array
     */
    public function getEnabledPermissions()
    {
        return array_keys(array_filter($this->permissions, function($value) {
            return $value === true;
        }));
    }

    /**
     * Set default permissions for system users (non-clients)
     */
    private function setDefaultUserPermissions()
    {
        // Grant basic system user permissions
        $basic_permissions = [
            'upload',
            'edit_files',
            'view_files',
            'view_clients',
            'view_groups',
        ];

        foreach ($basic_permissions as $permission) {
            if (isset($this->permissions[$permission])) {
                $this->permissions[$permission] = true;
            }
        }
    }
}