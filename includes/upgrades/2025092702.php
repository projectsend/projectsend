<?php
/**
 * Database upgrade: Add manage_custom_fields permission to all roles
 * This upgrade ensures all existing roles have the manage_custom_fields permission
 */

function upgrade_2025092702()
{
    global $dbh;

    try {
        // First, ensure the permission definition exists in tbl_permissions
        $permission_name = 'manage_custom_fields';

        // Check if permission definition exists
        $check_perm_query = "SELECT COUNT(*) FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission";
        $check_perm_stmt = $dbh->prepare($check_perm_query);
        $check_perm_stmt->bindParam(':permission', $permission_name, PDO::PARAM_STR);
        $check_perm_stmt->execute();
        $perm_exists = $check_perm_stmt->fetchColumn();

        if (!$perm_exists) {
            // Create the permission definition
            $insert_perm_query = "INSERT INTO " . TABLE_PERMISSIONS . "
                                  (permission_key, name, description, category, is_system_permission, active)
                                  VALUES (:permission_key, :name, :description, :category, 0, 1)";
            $insert_perm_stmt = $dbh->prepare($insert_perm_query);
            $insert_perm_stmt->execute([
                'permission_key' => $permission_name,
                'name' => 'Manage Custom Fields',
                'description' => 'Create, edit, and delete custom fields for users and clients',
                'category' => 'Custom Fields'
            ]);
        }

        // Add manage_custom_fields permission to all existing roles (enabled by default)

        // Check if we're using the new role_id system or old role_level system
        $check_column_sql = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_level'";
        $check_stmt = $dbh->prepare($check_column_sql);
        $check_stmt->execute();
        $has_role_level = $check_stmt->rowCount() > 0;

        if ($has_role_level) {
            // Old system: use role_level
            $role_levels = [0, 7, 8, 9]; // Client, Uploader, Account Manager, System Administrator

            foreach ($role_levels as $role_level) {
                // Check if permission already exists for this role
                $check_query = "SELECT COUNT(*) FROM " . TABLE_ROLE_PERMISSIONS . " WHERE role_level = :role_level AND permission = :permission";
                $check_stmt = $dbh->prepare($check_query);
                $check_stmt->bindParam(':role_level', $role_level, PDO::PARAM_INT);
                $check_stmt->bindParam(':permission', $permission_name, PDO::PARAM_STR);
                $check_stmt->execute();
                $exists = $check_stmt->fetchColumn();

                if (!$exists) {
                    // Add permission for this role (enabled by default)
                    $insert_query = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_level, permission, granted) VALUES (:role_level, :permission, 1)";
                    $insert_stmt = $dbh->prepare($insert_query);
                    $insert_stmt->bindParam(':role_level', $role_level, PDO::PARAM_INT);
                    $insert_stmt->bindParam(':permission', $permission_name, PDO::PARAM_STR);
                    $insert_stmt->execute();
                }
            }
        } else {
            // New system: use role_id
            $roles_query = "SELECT id FROM " . TABLE_ROLES;
            $roles_stmt = $dbh->prepare($roles_query);
            $roles_stmt->execute();
            $roles = $roles_stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($roles as $role_id) {
                // Check if permission already exists for this role
                $check_query = "SELECT COUNT(*) FROM " . TABLE_ROLE_PERMISSIONS . " WHERE role_id = :role_id AND permission = :permission";
                $check_stmt = $dbh->prepare($check_query);
                $check_stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
                $check_stmt->bindParam(':permission', $permission_name, PDO::PARAM_STR);
                $check_stmt->execute();
                $exists = $check_stmt->fetchColumn();

                if (!$exists) {
                    // Add permission for this role (enabled by default)
                    $insert_query = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_id, permission, granted) VALUES (:role_id, :permission, 1)";
                    $insert_stmt = $dbh->prepare($insert_query);
                    $insert_stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
                    $insert_stmt->bindParam(':permission', $permission_name, PDO::PARAM_STR);
                    $insert_stmt->execute();
                }
            }
        }

        error_log('Database upgrade 2025092702: Successfully added manage_custom_fields permission to all roles');

    } catch (Exception $e) {
        error_log('Database upgrade 2025092702 failed: ' . $e->getMessage());
        throw $e;
    }
}