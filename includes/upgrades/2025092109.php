<?php
function upgrade_2025092109()
{
    global $dbh;

    try {
        // Ensure edit_self_account permission exists
        $permission_sql = "SELECT COUNT(*) FROM " . TABLE_PERMISSIONS . " WHERE permission_key = 'edit_self_account'";
        $permission_stmt = $dbh->prepare($permission_sql);
        $permission_stmt->execute();

        if ($permission_stmt->fetchColumn() == 0) {
            // Create the permission if it doesn't exist
            $insert_permission_sql = "INSERT INTO " . TABLE_PERMISSIONS . "
                                    (permission_key, name, description, category, active, created_date)
                                    VALUES ('edit_self_account', 'Edit own account', 'Allow user to edit their own account details', 'users', 1, NOW())";
            $insert_permission_stmt = $dbh->prepare($insert_permission_sql);
            $insert_permission_stmt->execute();
            error_log("ProjectSend: Created edit_self_account permission");
        }

        // Get all roles
        $roles_sql = "SELECT id, name FROM " . TABLE_ROLES;
        $roles_stmt = $dbh->prepare($roles_sql);
        $roles_stmt->execute();
        $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_granted = 0;

        foreach ($roles as $role) {
            // Check if role already has this permission
            $check_sql = "SELECT COUNT(*) FROM " . TABLE_ROLE_PERMISSIONS . "
                         WHERE role_id = :role_id AND permission = 'edit_self_account'";
            $check_stmt = $dbh->prepare($check_sql);
            $check_stmt->execute(['role_id' => $role['id']]);

            if ($check_stmt->fetchColumn() == 0) {
                // Grant permission to role
                $grant_sql = "INSERT INTO " . TABLE_ROLE_PERMISSIONS . "
                             (role_id, permission, granted)
                             VALUES (:role_id, 'edit_self_account', 1)";
                $grant_stmt = $dbh->prepare($grant_sql);
                $grant_stmt->execute(['role_id' => $role['id']]);
                $total_granted++;
                error_log("ProjectSend: Granted edit_self_account permission to role '{$role['name']}'");
            } else {
                // Ensure it's set to granted (in case it was previously disabled)
                $update_sql = "UPDATE " . TABLE_ROLE_PERMISSIONS . "
                              SET granted = 1
                              WHERE role_id = :role_id AND permission = 'edit_self_account'";
                $update_stmt = $dbh->prepare($update_sql);
                $update_stmt->execute(['role_id' => $role['id']]);
            }
        }

        error_log("ProjectSend: edit_self_account permission consistency check completed. Granted to {$total_granted} new roles.");

    } catch (PDOException $e) {
        error_log("ProjectSend: Could not ensure edit_self_account permission consistency: " . $e->getMessage());
    }
}