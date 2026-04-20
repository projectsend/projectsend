<?php
/**
 * Migrate old client permission options to the new permission system
 * This converts the old get_option() based permissions to role-based permissions
 */
function upgrade_2025092111()
{
    global $dbh;

    // Get the Client role ID
    $query = "SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Client'";
    $statement = $dbh->prepare($query);
    $statement->execute();
    $client_role_id = $statement->fetchColumn();

    if (!$client_role_id) {
        error_log("ProjectSend: Could not find Client role during migration");
        return;
    }

    // Mapping of old options to new permissions
    $option_to_permission_map = [
        'clients_can_upload' => 'upload',
        'clients_can_delete_own_files' => 'delete_files',
        'clients_can_set_expiration_date' => 'set_file_expiration_date',
        'clients_can_upload_to_public_folders' => 'upload_to_public_folders',
        'clients_can_set_categories' => 'set_file_categories',
    ];

    // Ensure required permissions exist in the permissions table
    $required_permissions = [
        'upload_public' => ['Upload public files', 'Allow user to mark uploaded files as public', 'files'],
        'set_file_categories' => ['Set file categories', 'Allow user to assign categories to files', 'files']
    ];

    foreach ($required_permissions as $perm_key => $perm_data) {
        $check_perm = "SELECT COUNT(*) FROM " . TABLE_PERMISSIONS . " WHERE permission_key = :perm_key";
        $stmt = $dbh->prepare($check_perm);
        $stmt->execute(['perm_key' => $perm_key]);
        if ($stmt->fetchColumn() == 0) {
            // Create the permission
            $insert_perm = "INSERT INTO " . TABLE_PERMISSIONS . "
                            (permission_key, name, description, category, is_system_permission, active)
                            VALUES (:perm_key, :name, :description, :category, 1, 1)";
            $stmt = $dbh->prepare($insert_perm);
            $stmt->execute([
                'perm_key' => $perm_key,
                'name' => $perm_data[0],
                'description' => $perm_data[1],
                'category' => $perm_data[2]
            ]);
        }
    }

    // Process each option and sync with permissions
    foreach ($option_to_permission_map as $option_name => $permission_key) {
        $option_value = get_option($option_name);

        // Check if the permission exists for the Client role
        $check_query = "SELECT COUNT(*) FROM " . TABLE_ROLE_PERMISSIONS . "
                        WHERE role_id = :role_id AND permission = :permission";
        $check_stmt = $dbh->prepare($check_query);
        $check_stmt->execute([
            'role_id' => $client_role_id,
            'permission' => $permission_key
        ]);
        $exists = $check_stmt->fetchColumn() > 0;

        if ($option_value == '1' || $option_value == 'true') {
            // Option is enabled, ensure permission is granted
            if (!$exists) {
                $insert_query = "INSERT INTO " . TABLE_ROLE_PERMISSIONS . "
                                (role_id, permission, granted)
                                VALUES (:role_id, :permission, 1)";
                $insert_stmt = $dbh->prepare($insert_query);
                $insert_stmt->execute([
                    'role_id' => $client_role_id,
                    'permission' => $permission_key
                ]);
            } else {
                // Update to ensure it's granted
                $update_query = "UPDATE " . TABLE_ROLE_PERMISSIONS . "
                                SET granted = 1
                                WHERE role_id = :role_id AND permission = :permission";
                $update_stmt = $dbh->prepare($update_query);
                $update_stmt->execute([
                    'role_id' => $client_role_id,
                    'permission' => $permission_key
                ]);
            }
        } else {
            // Option is disabled, ensure permission is not granted
            if ($exists) {
                // Remove the permission
                $delete_query = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . "
                                WHERE role_id = :role_id AND permission = :permission";
                $delete_stmt = $dbh->prepare($delete_query);
                $delete_stmt->execute([
                    'role_id' => $client_role_id,
                    'permission' => $permission_key
                ]);
            }
        }
    }

    // Handle clients_can_set_public option (special case with values: none, allowed, required)
    $public_option = get_option('clients_can_set_public');

    // Check if upload_public permission exists for Client role
    $check_public = "SELECT COUNT(*) FROM " . TABLE_ROLE_PERMISSIONS . "
                     WHERE role_id = :role_id AND permission = 'upload_public'";
    $check_stmt = $dbh->prepare($check_public);
    $check_stmt->execute(['role_id' => $client_role_id]);
    $public_exists = $check_stmt->fetchColumn() > 0;

    if ($public_option == 'allowed' || $public_option == 'required') {
        // Grant upload_public permission
        if (!$public_exists) {
            $insert_query = "INSERT INTO " . TABLE_ROLE_PERMISSIONS . "
                            (role_id, permission, granted)
                            VALUES (:role_id, 'upload_public', 1)";
            $insert_stmt = $dbh->prepare($insert_query);
            $insert_stmt->execute(['role_id' => $client_role_id]);
        } else {
            $update_query = "UPDATE " . TABLE_ROLE_PERMISSIONS . "
                            SET granted = 1
                            WHERE role_id = :role_id AND permission = 'upload_public'";
            $update_stmt = $dbh->prepare($update_query);
            $update_stmt->execute(['role_id' => $client_role_id]);
        }
    } else {
        // Remove upload_public permission
        if ($public_exists) {
            $delete_query = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . "
                            WHERE role_id = :role_id AND permission = 'upload_public'";
            $delete_stmt = $dbh->prepare($delete_query);
            $delete_stmt->execute(['role_id' => $client_role_id]);
        }
    }

    // Mark the Client role as having editable permissions (only for specific ones)
    // We keep it as permissions_editable = 0 but handle it specially in the UI
    // This is already handled in the role-permissions.php file
}