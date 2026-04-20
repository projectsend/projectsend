<?php
/**
 * Grant edit_files permission to all roles
 * This is a fundamental permission that should be available to everyone
 * as users should always be able to edit their own uploaded files
 */
function upgrade_2025092110()
{
    global $dbh;

    // Check if we're using the new role_id system or old role_level system
    $check_column_sql = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_level'";
    $check_stmt = $dbh->prepare($check_column_sql);
    $check_stmt->execute();
    $has_role_level = $check_stmt->rowCount() > 0;

    if ($has_role_level) {
        // Old system: use role_level
        $role_levels = [0, 7, 8, 9]; // Client, Uploader, Account Manager, System Administrator

        foreach ($role_levels as $role_level) {
            // Check if the role already has edit_files permission
            $check_query = "SELECT COUNT(*) as count FROM " . TABLE_ROLE_PERMISSIONS . "
                            WHERE role_level = :role_level AND permission = 'edit_files'";
            $check_stmt = $dbh->prepare($check_query);
            $check_stmt->bindParam(':role_level', $role_level, PDO::PARAM_INT);
            $check_stmt->execute();
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

            // If not, add it
            if ($result['count'] == 0) {
                $insert_query = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_level, permission)
                               VALUES (:role_level, 'edit_files')";
                $insert_stmt = $dbh->prepare($insert_query);
                $insert_stmt->bindParam(':role_level', $role_level, PDO::PARAM_INT);
                $insert_stmt->execute();
            }
        }
    } else {
        // New system: use role_id
        $query = "SELECT id FROM " . TABLE_ROLES;
        $statement = $dbh->query($query);
        $roles = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roles as $role) {
            // Check if the role already has edit_files permission
            $check_query = "SELECT COUNT(*) as count FROM " . TABLE_ROLE_PERMISSIONS . "
                            WHERE role_id = :role_id AND permission = 'edit_files'";
            $check_stmt = $dbh->prepare($check_query);
            $check_stmt->bindParam(':role_id', $role['id'], PDO::PARAM_INT);
            $check_stmt->execute();
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

            // If not, add it
            if ($result['count'] == 0) {
                $insert_query = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_id, permission)
                               VALUES (:role_id, 'edit_files')";
                $insert_stmt = $dbh->prepare($insert_query);
                $insert_stmt->bindParam(':role_id', $role['id'], PDO::PARAM_INT);
                $insert_stmt->execute();
            }
        }
    }

    // Update the permission description to clarify it's for own files
    $update_desc = "UPDATE " . TABLE_PERMISSIONS . "
                    SET name = 'Edit own files',
                        description = 'Allow user to edit their own uploaded files (always granted)'
                    WHERE permission_key = 'edit_files'";
    $dbh->exec($update_desc);
}