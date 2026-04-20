<?php
function upgrade_2025092106()
{
    global $dbh;

    // Add new widget permissions to the permissions table
    $new_permissions = [
        [
            'permission_key' => 'view_update_center',
            'name' => 'View update center',
            'description' => 'Allow user to view system updates and version information',
            'category' => 'dashboard'
        ],
        [
            'permission_key' => 'view_storage_analytics',
            'name' => 'View storage analytics',
            'description' => 'Allow user to view storage usage and file analytics',
            'category' => 'dashboard'
        ],
        [
            'permission_key' => 'view_file_management_summary',
            'name' => 'View file management summary',
            'description' => 'Allow user to view file management overview and recent activity',
            'category' => 'dashboard'
        ],
        [
            'permission_key' => 'view_download_analytics',
            'name' => 'View download analytics',
            'description' => 'Allow user to view download statistics and trends',
            'category' => 'dashboard'
        ],
        [
            'permission_key' => 'view_my_files',
            'name' => 'View my files widget',
            'description' => 'Allow user to view personal files summary widget',
            'category' => 'dashboard'
        ],
        [
            'permission_key' => 'view_personal_storage',
            'name' => 'View personal storage',
            'description' => 'Allow user to view personal storage usage information',
            'category' => 'dashboard'
        ],
        [
            'permission_key' => 'view_recent_downloads',
            'name' => 'View recent downloads',
            'description' => 'Allow user to view recent download activity',
            'category' => 'dashboard'
        ]
    ];

    foreach ($new_permissions as $permission) {
        // Check if permission already exists
        $check_sql = "SELECT COUNT(*) FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission_key";
        $check_stmt = $dbh->prepare($check_sql);
        $check_stmt->execute(['permission_key' => $permission['permission_key']]);

        if ($check_stmt->fetchColumn() == 0) {
            // Insert new permission
            $insert_sql = "INSERT INTO " . TABLE_PERMISSIONS . "
                          (permission_key, name, description, category, active, created_date)
                          VALUES (:permission_key, :name, :description, :category, 1, NOW())";
            $insert_stmt = $dbh->prepare($insert_sql);
            $insert_stmt->execute($permission);
        }
    }

    // Grant new dashboard widget permissions to System Administrator and Account Manager roles by default
    $roles_to_update = [
        'System Administrator' => [
            'view_update_center', 'view_storage_analytics', 'view_file_management_summary',
            'view_download_analytics', 'view_my_files', 'view_personal_storage', 'view_recent_downloads'
        ],
        'Account Manager' => [
            'view_storage_analytics', 'view_file_management_summary',
            'view_download_analytics', 'view_my_files', 'view_personal_storage', 'view_recent_downloads'
        ]
    ];

    foreach ($roles_to_update as $role_name => $permissions) {
        // Get role ID by name
        $role_sql = "SELECT id FROM " . TABLE_ROLES . " WHERE name = :name";
        $role_stmt = $dbh->prepare($role_sql);
        $role_stmt->execute(['name' => $role_name]);
        $role_id = $role_stmt->fetchColumn();

        if ($role_id) {
            foreach ($permissions as $permission) {
                // Only insert if permission exists in permissions table
                $check_permission_sql = "SELECT id FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission AND active = 1";
                $perm_stmt = $dbh->prepare($check_permission_sql);
                $perm_stmt->execute(['permission' => $permission]);

                if ($perm_stmt->fetch()) {
                    // Check if permission assignment already exists
                    $check_perm_sql = "SELECT COUNT(*) FROM " . TABLE_ROLE_PERMISSIONS . "
                                      WHERE role_id = :role_id AND permission = :permission";
                    $check_perm_stmt = $dbh->prepare($check_perm_sql);
                    $check_perm_stmt->execute(['role_id' => $role_id, 'permission' => $permission]);

                    if ($check_perm_stmt->fetchColumn() == 0) {
                        // Grant permission to role
                        $grant_sql = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
                                     (role_id, permission, granted)
                                     VALUES (:role_id, :permission, 1)";
                        $grant_stmt = $dbh->prepare($grant_sql);
                        $grant_stmt->execute(['role_id' => $role_id, 'permission' => $permission]);
                    }
                }
            }
        }
    }
}