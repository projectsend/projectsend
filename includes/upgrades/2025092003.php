<?php
/**
 * Database upgrade for User-Role Foreign Key Migration
 * Adds role_id foreign key to users table and migrates existing level data
 */

function upgrade_2025092003()
{
    global $dbh;

    // Add role_id column to users table
    $add_role_id_sql = "ALTER TABLE " . TABLE_USERS . "
                        ADD COLUMN role_id int(11) DEFAULT NULL AFTER level";

    try {
        $statement = $dbh->prepare($add_role_id_sql);
        $statement->execute();
    } catch (PDOException $e) {
        // Column might already exist, check if it's a duplicate column error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e; // Re-throw if it's not a duplicate column error
        }
    }

    // Migrate existing user levels to role_id relationships
    $migration_mappings = [
        9 => 4, // System Administrator (role ID 4)
        8 => 3, // Account Manager (role ID 3)
        7 => 2, // Uploader (role ID 2)
        0 => 1  // Client (role ID 1)
    ];

    foreach ($migration_mappings as $old_level => $new_role_id) {
        $update_sql = "UPDATE " . TABLE_USERS . "
                       SET role_id = :role_id
                       WHERE level = :level AND role_id IS NULL";

        $statement = $dbh->prepare($update_sql);
        $statement->execute([
            'role_id' => $new_role_id,
            'level' => $old_level
        ]);
    }

    // Handle any users with unexpected levels (map them to lowest privilege role)
    $cleanup_sql = "UPDATE " . TABLE_USERS . "
                    SET role_id = 1
                    WHERE role_id IS NULL AND level NOT IN (9, 8, 7, 0)";

    $statement = $dbh->prepare($cleanup_sql);
    $statement->execute();

    // Add foreign key constraint (after data migration)
    try {
        $fk_sql = "ALTER TABLE " . TABLE_USERS . "
                   ADD CONSTRAINT fk_users_role
                   FOREIGN KEY (role_id) REFERENCES " . TABLE_ROLES . "(id)
                   ON UPDATE CASCADE ON DELETE RESTRICT";

        $statement = $dbh->prepare($fk_sql);
        $statement->execute();
    } catch (PDOException $e) {
        // Foreign key might already exist
        if (strpos($e->getMessage(), 'Duplicate foreign key constraint name') === false) {
            error_log("ProjectSend: Could not add foreign key constraint: " . $e->getMessage());
        }
    }

    // Add index for performance
    try {
        $index_sql = "ALTER TABLE " . TABLE_USERS . "
                      ADD INDEX idx_role_id (role_id)";

        $statement = $dbh->prepare($index_sql);
        $statement->execute();
    } catch (PDOException $e) {
        // Index might already exist
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            error_log("ProjectSend: Could not add role_id index: " . $e->getMessage());
        }
    }

    // Update system options to track migration
    add_option_if_not_exists('users_role_migration_completed', '1');

    error_log("ProjectSend: User-Role migration completed successfully");
}