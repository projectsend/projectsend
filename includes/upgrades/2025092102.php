<?php
function upgrade_2025092102()
{
    global $dbh;

    // Check if permissions_editable column already exists
    $check_query = "SHOW COLUMNS FROM " . TABLE_ROLES . " LIKE 'permissions_editable'";
    $check_statement = $dbh->prepare($check_query);
    $check_statement->execute();

    if ($check_statement->rowCount() == 0) {
        // Add permissions_editable column to roles table if it doesn't exist
        $query = "ALTER TABLE " . TABLE_ROLES . " ADD COLUMN permissions_editable TINYINT(1) DEFAULT 1 AFTER is_system_role";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }

    // Set Client role (id=1) and System Administrator role (id=4) as non-editable
    $query = "UPDATE " . TABLE_ROLES . " SET permissions_editable = 0 WHERE name IN ('Client', 'System Administrator')";
    $statement = $dbh->prepare($query);
    $statement->execute();
}