<?php
/**
 * Database upgrade for Enhanced Permission System
 * Creates permissions table and populates it with all available permissions
 * Ensures System Admin always has ALL permissions
 */

function upgrade_2025092002()
{
    global $dbh;

    // Create permissions table to store individual permission definitions
    $permissions_table_sql = "CREATE TABLE IF NOT EXISTS " . TABLE_PERMISSIONS . " (
        id int(11) NOT NULL AUTO_INCREMENT,
        permission_key varchar(255) NOT NULL UNIQUE,
        name varchar(255) NOT NULL,
        description text,
        category varchar(100) NOT NULL,
        is_system_permission tinyint(1) DEFAULT 1,
        active tinyint(1) DEFAULT 1,
        created_date timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_permission_key (permission_key),
        KEY idx_category (category),
        KEY idx_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    $statement = $dbh->prepare($permissions_table_sql);
    $statement->execute();

    // Get all available permissions from the static definition
    $available_permissions = \ProjectSend\Classes\Permissions::getCorePermissionsDefinition();

    // Insert each permission into the permissions table
    foreach ($available_permissions as $permission_key => $permission_data) {
        $insert_permission_sql = "INSERT IGNORE INTO " . TABLE_PERMISSIONS . "
            (permission_key, name, description, category, is_system_permission, active)
            VALUES (:permission_key, :name, :description, :category, 1, 1)";

        $statement = $dbh->prepare($insert_permission_sql);
        $statement->execute([
            'permission_key' => $permission_key,
            'name' => $permission_data['label'] ?? $permission_key,
            'description' => $permission_data['description'] ?? '',
            'category' => $permission_data['category'] ?? 'general'
        ]);
    }

    // Clear existing role permissions to rebuild them properly
    $clear_sql = "DELETE FROM " . TABLE_ROLE_PERMISSIONS;
    $statement = $dbh->prepare($clear_sql);
    $statement->execute();

    // Get all permissions from the new permissions table
    $get_all_permissions_sql = "SELECT permission_key FROM " . TABLE_PERMISSIONS . " WHERE active = 1";
    $statement = $dbh->prepare($get_all_permissions_sql);
    $statement->execute();
    $all_permissions = $statement->fetchAll(PDO::FETCH_COLUMN);

    // System Admin (level 9) ALWAYS gets ALL permissions - this is non-negotiable
    foreach ($all_permissions as $permission) {
        $insert_admin_permission_sql = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
            (role_level, permission, granted)
            VALUES (9, :permission, 1)";

        $statement = $dbh->prepare($insert_admin_permission_sql);
        $statement->execute(['permission' => $permission]);
    }

    // Set default permissions for other system roles based on original logic
    $default_role_permissions = [
        8 => [ // Account Manager
            'upload', 'edit_files', 'edit_others_files', 'delete_files', 'delete_others_files',
            'set_file_expiration_date', 'upload_public', 'import_orphans',
            'create_categories', 'edit_categories', 'delete_categories',
            'create_clients', 'edit_clients', 'delete_clients',
            'edit_self_account', 'approve_account_requests',
            'create_groups', 'edit_groups', 'delete_groups', 'approve_groups_memberships_requests',
            'view_actions_log', 'view_statistics', 'view_news', 'unblock_ip'
        ],
        7 => [ // Uploader
            'upload', 'edit_files', 'delete_files', 'set_file_expiration_date',
            'upload_public', 'import_orphans',
            'create_categories', 'edit_categories', 'delete_categories',
            'edit_self_account',
            'view_actions_log', 'view_statistics', 'view_news'
        ],
        0 => [ // Client - will be dynamically set based on options
            'edit_self_account'
        ]
    ];

    // Insert default permissions for system roles
    foreach ($default_role_permissions as $role_level => $permissions) {
        foreach ($permissions as $permission) {
            // Only insert if permission exists in permissions table
            $check_permission_sql = "SELECT id FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission AND active = 1";
            $statement = $dbh->prepare($check_permission_sql);
            $statement->execute(['permission' => $permission]);

            if ($statement->fetch()) {
                $insert_permission_sql = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
                    (role_level, permission, granted)
                    VALUES (:role_level, :permission, 1)";

                $statement = $dbh->prepare($insert_permission_sql);
                $statement->execute([
                    'role_level' => $role_level,
                    'permission' => $permission
                ]);
            }
        }
    }

    // Set client permissions based on current system options
    $client_permissions = [];

    if (get_option('clients_can_upload') == 1) {
        $client_permissions[] = 'upload';
        $client_permissions[] = 'edit_files';
    }

    if (get_option('clients_can_delete_own_files') == 1) {
        $client_permissions[] = 'delete_files';
    }

    if (get_option('clients_can_set_expiration_date') == 1) {
        $client_permissions[] = 'set_file_expiration_date';
    }

    if (get_option('clients_can_set_categories') == 1) {
        $client_permissions[] = 'set_file_categories';
    }

    if (get_option('clients_can_upload_to_public_folders') == 1) {
        $client_permissions[] = 'upload_to_public_folders';
    }

    // Insert client permissions
    foreach ($client_permissions as $permission) {
        $check_permission_sql = "SELECT id FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission AND active = 1";
        $statement = $dbh->prepare($check_permission_sql);
        $statement->execute(['permission' => $permission]);

        if ($statement->fetch()) {
            $insert_permission_sql = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
                (role_level, permission, granted)
                VALUES (0, :permission, 1)";

            $statement = $dbh->prepare($insert_permission_sql);
            $statement->execute(['permission' => $permission]);
        }
    }

    // Add option to track permission system version
    add_option_if_not_exists('permission_system_version', '2');
    add_option_if_not_exists('auto_create_missing_permissions', '1');
}