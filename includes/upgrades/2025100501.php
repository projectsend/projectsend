<?php
function upgrade_2025100501()
{
    global $dbh;

    // Add download limit columns to files table
    $query = "ALTER TABLE " . TABLE_FILES . "
        ADD COLUMN download_limit_enabled TINYINT(1) DEFAULT 0 AFTER public_token,
        ADD COLUMN download_limit_type VARCHAR(20) DEFAULT 'total' AFTER download_limit_enabled,
        ADD COLUMN download_limit_count INT(11) DEFAULT 0 AFTER download_limit_type";
    $statement = $dbh->prepare($query);
    $statement->execute();

    // Add limit_downloads permission for Client role
    // Check if we're using the new role_id system or old role_level system
    $check_column_sql = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_level'";
    $check_stmt = $dbh->prepare($check_column_sql);
    $check_stmt->execute();
    $has_role_level = $check_stmt->rowCount() > 0;

    if ($has_role_level) {
        // Old system: use role_level (0 = Client)
        $query = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_level, permission, granted)
                  VALUES (0, :permission, 1)";
        $statement = $dbh->prepare($query);
        $statement->bindValue(':permission', 'limit_downloads', PDO::PARAM_STR);
        $statement->execute();
    } else {
        // New system: use role_id
        $query = "SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Client' LIMIT 1";
        $statement = $dbh->prepare($query);
        $statement->execute();
        $client_role = $statement->fetch(PDO::FETCH_ASSOC);

        if ($client_role) {
            $client_role_id = $client_role['id'];

            // Assign permission to Client role (using permission name, not ID)
            $query = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_id, permission, granted)
                      VALUES (:role_id, :permission, 1)";
            $statement = $dbh->prepare($query);
            $statement->bindParam(':role_id', $client_role_id, PDO::PARAM_INT);
            $statement->bindValue(':permission', 'limit_downloads', PDO::PARAM_STR);
            $statement->execute();
        }
    }
}
