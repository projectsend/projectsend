<?php
function upgrade_2025092401()
{
    global $dbh;

    try {
        // Add manage_updates permission to database if it doesn't exist
        $permission_sql = "SELECT COUNT(*) FROM " . TABLE_PERMISSIONS . " WHERE permission_key = 'manage_updates'";
        $permission_stmt = $dbh->prepare($permission_sql);
        $permission_stmt->execute();

        if ($permission_stmt->fetchColumn() == 0) {
            $insert_permission_sql = "INSERT INTO " . TABLE_PERMISSIONS . "
                                    (permission_key, name, description, category, active, created_date)
                                    VALUES ('manage_updates', 'Manage system updates', 'Allow user to download and install system updates', 'system', 1, NOW())";
            $insert_permission_stmt = $dbh->prepare($insert_permission_sql);
            $insert_permission_stmt->execute();
            error_log("ProjectSend: Created manage_updates permission");
        }

        // Grant permission to System Administrator role automatically
        $check_sql = "SELECT COUNT(*) FROM " . TABLE_ROLE_PERMISSIONS . " rp
                     INNER JOIN " . TABLE_ROLES . " r ON rp.role_id = r.id
                     WHERE r.name = 'System Administrator' AND rp.permission = 'manage_updates'";
        $check_stmt = $dbh->prepare($check_sql);
        $check_stmt->execute();

        if ($check_stmt->fetchColumn() == 0) {
            // Get System Administrator role ID
            $role_sql = "SELECT id FROM " . TABLE_ROLES . " WHERE name = 'System Administrator'";
            $role_stmt = $dbh->prepare($role_sql);
            $role_stmt->execute();
            $admin_role = $role_stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin_role) {
                $grant_sql = "INSERT INTO " . TABLE_ROLE_PERMISSIONS . "
                             (role_id, permission, granted)
                             VALUES (:role_id, 'manage_updates', 1)";
                $grant_stmt = $dbh->prepare($grant_sql);
                $grant_stmt->execute(['role_id' => $admin_role['id']]);
                error_log("ProjectSend: Granted manage_updates permission to System Administrator role");
            }
        }

    } catch (PDOException $e) {
        error_log("ProjectSend: Could not create manage_updates permission: " . $e->getMessage());
    }
}