<?php
function upgrade_2025092108()
{
    global $dbh;

    // Remove permissions for ALL deleted widgets
    $permissions_to_remove = [
        'view_update_center',           // Update Center widget (deleted - redundant)
        'view_my_files',                // My Files widget (deleted - merged into Personal Storage)
        'view_recent_downloads',        // Recent Downloads widget (deleted)
        'view_file_management_summary', // File Management Summary widget (deleted)
        'view_personal_storage'         // Personal Storage widget (deleted)
    ];

    $total_removed = 0;
    $total_role_assignments_removed = 0;

    foreach ($permissions_to_remove as $permission_key) {
        try {
            // First remove role permissions and count them
            $delete_role_perms_sql = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE permission = :permission_key";
            $delete_role_perms_stmt = $dbh->prepare($delete_role_perms_sql);
            $delete_role_perms_stmt->execute(['permission_key' => $permission_key]);
            $role_assignments_removed = $delete_role_perms_stmt->rowCount();
            $total_role_assignments_removed += $role_assignments_removed;

            // Then remove the permission itself
            $delete_permission_sql = "DELETE FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :permission_key";
            $delete_permission_stmt = $dbh->prepare($delete_permission_sql);
            $delete_permission_stmt->execute(['permission_key' => $permission_key]);
            $permissions_removed = $delete_permission_stmt->rowCount();
            $total_removed += $permissions_removed;

            if ($permissions_removed > 0) {
                error_log("ProjectSend: Removed permission '{$permission_key}' and {$role_assignments_removed} role assignments");
            }
        } catch (PDOException $e) {
            error_log("ProjectSend: Could not remove permission '{$permission_key}': " . $e->getMessage());
        }
    }

    error_log("ProjectSend: Widget permissions cleanup completed. Removed {$total_removed} permissions and {$total_role_assignments_removed} role assignments.");
}