<?php
/**
 * Major Database Restructure: Migrate from role_level to ID-based roles
 * This migration completely removes dependency on role levels (0,7,8,9)
 * and replaces it with proper database IDs
 */

function upgrade_2025092004()
{
    global $dbh;

    // Step 1: Check if roles table exists with proper structure
    if (!table_exists(TABLE_ROLES)) {
        // Create roles table with ID as primary key (not role_level)
        $create_roles_sql = "CREATE TABLE IF NOT EXISTS " . TABLE_ROLES . " (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            is_system_role tinyint(1) DEFAULT 0,
            active tinyint(1) DEFAULT 1,
            created_date timestamp DEFAULT CURRENT_TIMESTAMP,
            modified_date timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_role_name (name),
            KEY idx_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        $statement = $dbh->prepare($create_roles_sql);
        $statement->execute();
    } else {
        // Check if id column exists, if not add it
        $check_id_sql = "SHOW COLUMNS FROM " . TABLE_ROLES . " LIKE 'id'";
        $statement = $dbh->prepare($check_id_sql);
        $statement->execute();

        if ($statement->rowCount() == 0) {
            // Add id column as primary key
            $add_id_sql = "ALTER TABLE " . TABLE_ROLES . "
                          ADD COLUMN id int(11) NOT NULL AUTO_INCREMENT FIRST,
                          ADD PRIMARY KEY (id)";
            $statement = $dbh->prepare($add_id_sql);
            $statement->execute();
        }
    }

    // Step 2: Insert or update default system roles
    $default_roles = [
        ['name' => 'System Administrator', 'description' => 'Full system access with all permissions', 'is_system' => 1],
        ['name' => 'Account Manager', 'description' => 'Can manage clients and files', 'is_system' => 1],
        ['name' => 'Uploader', 'description' => 'Can upload and manage own files', 'is_system' => 1],
        ['name' => 'Client', 'description' => 'External users who receive files', 'is_system' => 1]
    ];

    $role_mapping = []; // Will store old_level => new_id mapping

    foreach ($default_roles as $role_data) {
        // Check if role exists by name
        $check_sql = "SELECT id FROM " . TABLE_ROLES . " WHERE name = :name";
        $statement = $dbh->prepare($check_sql);
        $statement->execute(['name' => $role_data['name']]);

        if ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $role_id = $row['id'];
        } else {
            // Insert new role
            $insert_sql = "INSERT INTO " . TABLE_ROLES . "
                          (name, description, is_system_role, active)
                          VALUES (:name, :description, :is_system, 1)";
            $statement = $dbh->prepare($insert_sql);
            $statement->execute([
                'name' => $role_data['name'],
                'description' => $role_data['description'],
                'is_system' => $role_data['is_system']
            ]);
            $role_id = $dbh->lastInsertId();
        }

        // Map old levels to new IDs
        switch ($role_data['name']) {
            case 'System Administrator':
                $role_mapping[9] = $role_id;
                break;
            case 'Account Manager':
                $role_mapping[8] = $role_id;
                break;
            case 'Uploader':
                $role_mapping[7] = $role_id;
                break;
            case 'Client':
                $role_mapping[0] = $role_id;
                break;
        }
    }

    // Step 3: Update role_permissions table to use role_id instead of role_level
    if (table_exists(TABLE_ROLE_PERMISSIONS)) {
        // Check if role_id column exists
        $check_role_id_sql = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_id'";
        $statement = $dbh->prepare($check_role_id_sql);
        $statement->execute();

        if ($statement->rowCount() == 0) {
            // Add role_id column
            $add_role_id_sql = "ALTER TABLE " . TABLE_ROLE_PERMISSIONS . "
                               ADD COLUMN role_id int(11) AFTER role_level";
            $statement = $dbh->prepare($add_role_id_sql);
            $statement->execute();

            // Migrate data from role_level to role_id
            foreach ($role_mapping as $old_level => $new_id) {
                $update_sql = "UPDATE " . TABLE_ROLE_PERMISSIONS . "
                              SET role_id = :role_id
                              WHERE role_level = :role_level";
                $statement = $dbh->prepare($update_sql);
                $statement->execute([
                    'role_id' => $new_id,
                    'role_level' => $old_level
                ]);
            }

            // Drop role_level column and update primary key
            try {
                $alter_sql = "ALTER TABLE " . TABLE_ROLE_PERMISSIONS . "
                             DROP COLUMN role_level,
                             DROP PRIMARY KEY,
                             ADD PRIMARY KEY (role_id, permission)";
                $statement = $dbh->prepare($alter_sql);
                $statement->execute();
            } catch (PDOException $e) {
                // Handle if columns already modified
                error_log("ProjectSend: Could not modify role_permissions table structure: " . $e->getMessage());
            }
        }
    }

    // Step 4: Update users table to use role_id properly
    if (!column_exists(TABLE_USERS, 'role_id')) {
        $add_role_id_sql = "ALTER TABLE " . TABLE_USERS . "
                           ADD COLUMN role_id int(11) DEFAULT NULL AFTER level";
        try {
            $statement = $dbh->prepare($add_role_id_sql);
            $statement->execute();
        } catch (PDOException $e) {
            // Column might already exist
        }
    }

    // Migrate user levels to role IDs
    foreach ($role_mapping as $old_level => $new_id) {
        $update_users_sql = "UPDATE " . TABLE_USERS . "
                            SET role_id = :role_id
                            WHERE level = :level AND (role_id IS NULL OR role_id = 0)";
        $statement = $dbh->prepare($update_users_sql);
        $statement->execute([
            'role_id' => $new_id,
            'level' => $old_level
        ]);
    }

    // Handle any custom roles that were created with role_level
    if (column_exists(TABLE_ROLES, 'role_level')) {
        // Map custom roles
        $custom_roles_sql = "SELECT id, role_level FROM " . TABLE_ROLES . "
                            WHERE role_level NOT IN (0, 7, 8, 9) AND role_level IS NOT NULL";
        $statement = $dbh->prepare($custom_roles_sql);
        $statement->execute();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // Update users with this custom role_level to use the role id
            $update_custom_sql = "UPDATE " . TABLE_USERS . "
                                 SET role_id = :role_id
                                 WHERE level = :level";
            $stmt = $dbh->prepare($update_custom_sql);
            $stmt->execute([
                'role_id' => $row['id'],
                'level' => $row['role_level']
            ]);

            // Update role_permissions for custom roles
            if (table_exists(TABLE_ROLE_PERMISSIONS)) {
                $update_perms_sql = "UPDATE " . TABLE_ROLE_PERMISSIONS . "
                                    SET role_id = :role_id
                                    WHERE role_level = :role_level";
                $stmt = $dbh->prepare($update_perms_sql);
                $stmt->execute([
                    'role_id' => $row['id'],
                    'role_level' => $row['role_level']
                ]);
            }
        }

        // Now we can safely drop the role_level column from roles table
        try {
            $drop_level_sql = "ALTER TABLE " . TABLE_ROLES . " DROP COLUMN role_level";
            $statement = $dbh->prepare($drop_level_sql);
            $statement->execute();
        } catch (PDOException $e) {
            // Column might not exist
        }
    }

    // Step 5: Add foreign key constraint from users to roles
    try {
        // First drop any existing foreign key
        $drop_fk_sql = "ALTER TABLE " . TABLE_USERS . " DROP FOREIGN KEY fk_users_role";
        $statement = $dbh->prepare($drop_fk_sql);
        $statement->execute();
    } catch (PDOException $e) {
        // Foreign key might not exist
    }

    try {
        $add_fk_sql = "ALTER TABLE " . TABLE_USERS . "
                      ADD CONSTRAINT fk_users_role
                      FOREIGN KEY (role_id) REFERENCES " . TABLE_ROLES . "(id)
                      ON UPDATE CASCADE ON DELETE RESTRICT";
        $statement = $dbh->prepare($add_fk_sql);
        $statement->execute();
    } catch (PDOException $e) {
        error_log("ProjectSend: Could not add foreign key constraint: " . $e->getMessage());
    }

    // Step 6: Ensure System Administrator role has ALL permissions
    $admin_role_sql = "SELECT id FROM " . TABLE_ROLES . " WHERE name = 'System Administrator'";
    $statement = $dbh->prepare($admin_role_sql);
    $statement->execute();

    if ($admin_row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $admin_role_id = $admin_row['id'];

        // Get all permissions
        if (table_exists(TABLE_PERMISSIONS)) {
            $all_perms_sql = "SELECT permission_key FROM " . TABLE_PERMISSIONS . " WHERE active = 1";
            $statement = $dbh->prepare($all_perms_sql);
            $statement->execute();

            while ($perm_row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $insert_perm_sql = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . "
                                   (role_id, permission, granted)
                                   VALUES (:role_id, :permission, 1)";
                $stmt = $dbh->prepare($insert_perm_sql);
                $stmt->execute([
                    'role_id' => $admin_role_id,
                    'permission' => $perm_row['permission_key']
                ]);
            }
        }
    }

    // Step 7: Clean up and add tracking option
    add_option_if_not_exists('roles_migration_to_id_completed', '1');
    add_option_if_not_exists('legacy_role_levels_removed', '1');

    error_log("ProjectSend: Successfully migrated from role_level to ID-based roles system");
}

/**
 * Helper function to check if a column exists
 */
function column_exists($table, $column) {
    global $dbh;
    $sql = "SHOW COLUMNS FROM $table LIKE '$column'";
    $statement = $dbh->prepare($sql);
    $statement->execute();
    return $statement->rowCount() > 0;
}