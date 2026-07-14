<?php
/**
 * Add create_own_folders permission and grant it to existing roles by default.
 */
function upgrade_2026060301()
{
    global $dbh;

    $permission_key = 'create_own_folders';

    $check_permission = "SELECT COUNT(*) FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission_key";
    $statement = $dbh->prepare($check_permission);
    $statement->execute(['permission_key' => $permission_key]);

    if ($statement->fetchColumn() == 0) {
        $insert_permission = "INSERT INTO " . TABLE_PERMISSIONS . "
            (permission_key, name, description, category, is_system_permission, active)
            VALUES (:permission_key, :name, :description, :category, 1, 1)";
        $statement = $dbh->prepare($insert_permission);
        $statement->execute([
            'permission_key' => $permission_key,
            'name' => __('Create own folders', 'cftp_admin'),
            'description' => __('Allow user to create folders for organizing their own files', 'cftp_admin'),
            'category' => 'files',
        ]);
    }

    $check_column = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_id'";
    $statement = $dbh->prepare($check_column);
    $statement->execute();
    $has_role_id = $statement->rowCount() > 0;

    $check_column = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_level'";
    $statement = $dbh->prepare($check_column);
    $statement->execute();
    $has_role_level = $statement->rowCount() > 0;

    if ($has_role_id && $has_role_level && table_exists(TABLE_ROLES)) {
        $role_levels = [
            'Client' => 0,
            'Uploader' => 7,
            'Account Manager' => 8,
            'System Administrator' => 9,
        ];

        $roles_query = "SELECT id, name FROM " . TABLE_ROLES;
        $statement = $dbh->prepare($roles_query);
        $statement->execute();
        $roles = $statement->fetchAll(PDO::FETCH_ASSOC);

        $update_permission = "UPDATE " . TABLE_ROLE_PERMISSIONS . "
            SET role_id = :role_id, granted = 1
            WHERE role_level = :role_level AND permission = :permission";
        $update_statement = $dbh->prepare($update_permission);

        $insert_permission = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
            (role_level, role_id, permission, granted)
            VALUES (:role_level, :role_id, :permission, 1)";
        $insert_statement = $dbh->prepare($insert_permission);

        foreach ($roles as $role) {
            if (!isset($role_levels[$role['name']])) {
                continue;
            }

            $params = [
                'role_level' => $role_levels[$role['name']],
                'role_id' => $role['id'],
                'permission' => $permission_key,
            ];

            $update_statement->execute($params);
            $insert_statement->execute($params);
        }

        return;
    }

    if ($has_role_id && table_exists(TABLE_ROLES)) {
        $roles_query = "SELECT id FROM " . TABLE_ROLES;
        $statement = $dbh->prepare($roles_query);
        $statement->execute();
        $roles = $statement->fetchAll(PDO::FETCH_COLUMN);

        $grant_permission = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
            (role_id, permission, granted)
            VALUES (:role_id, :permission, 1)";
        $statement = $dbh->prepare($grant_permission);

        foreach ($roles as $role_id) {
            $statement->execute([
                'role_id' => $role_id,
                'permission' => $permission_key,
            ]);
        }

        return;
    }

    if ($has_role_level) {
        $role_levels = [0, 7, 8, 9];
        $grant_permission = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
            (role_level, permission, granted)
            VALUES (:role_level, :permission, 1)";
        $statement = $dbh->prepare($grant_permission);

        foreach ($role_levels as $role_level) {
            $statement->execute([
                'role_level' => $role_level,
                'permission' => $permission_key,
            ]);
        }

        return;
    }
}
