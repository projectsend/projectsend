<?php
/**
 * Database upgrade for Role-Based Permission System
 * Creates roles and role_permissions tables
 * Populates default roles and permissions
 */

function upgrade_2025092001()
{
    global $dbh;

    // Create roles table
    $roles_table_sql = "CREATE TABLE IF NOT EXISTS " . TABLE_ROLES . " (
        id int(11) NOT NULL AUTO_INCREMENT,
        role_level int(11) NOT NULL UNIQUE,
        name varchar(255) NOT NULL,
        description text,
        is_system_role tinyint(1) DEFAULT 0,
        active tinyint(1) DEFAULT 1,
        created_date timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_role_level (role_level),
        KEY idx_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    $statement = $dbh->prepare($roles_table_sql);
    $statement->execute();

    // Create role_permissions table
    $role_permissions_table_sql = "CREATE TABLE IF NOT EXISTS " . TABLE_ROLE_PERMISSIONS . " (
        id int(11) NOT NULL AUTO_INCREMENT,
        role_level int(11) NOT NULL,
        permission varchar(255) NOT NULL,
        granted tinyint(1) DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY role_permission (role_level, permission),
        KEY idx_role_level (role_level),
        KEY idx_permission (permission)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    $statement = $dbh->prepare($role_permissions_table_sql);
    $statement->execute();

    // Insert default roles
    $default_roles = [
        [
            'role_level' => 0,
            'name' => 'Client',
            'description' => 'Client users who receive files',
            'is_system_role' => 1,
            'active' => 1
        ],
        [
            'role_level' => 7,
            'name' => 'Uploader',
            'description' => 'Users who can upload and manage their own files',
            'is_system_role' => 1,
            'active' => 1
        ],
        [
            'role_level' => 8,
            'name' => 'Account Manager',
            'description' => 'Users who can manage clients and files',
            'is_system_role' => 1,
            'active' => 1
        ],
        [
            'role_level' => 9,
            'name' => 'System Administrator',
            'description' => 'Full system access and management',
            'is_system_role' => 1,
            'active' => 1
        ]
    ];

    foreach ($default_roles as $role) {
        $insert_role_sql = "INSERT IGNORE INTO " . TABLE_ROLES . "
            (role_level, name, description, is_system_role, active)
            VALUES (:role_level, :name, :description, :is_system_role, :active)";

        $statement = $dbh->prepare($insert_role_sql);
        $statement->execute($role);
    }

    // Note: Permissions will be properly populated by migration 2025092002.php
    // which creates the permissions table and assigns them correctly

    // Add options for role management
    add_option_if_not_exists('enable_custom_roles', '1');
    add_option_if_not_exists('default_new_user_role', '7');
    add_option_if_not_exists('role_system_version', '1');
}