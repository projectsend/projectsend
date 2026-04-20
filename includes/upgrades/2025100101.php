<?php
function upgrade_2025100101()
{
    global $dbh;

    // Add max_disk_quota column to users table
    $add_quota_column_sql = "ALTER TABLE " . TABLE_USERS . "
                           ADD COLUMN max_disk_quota BIGINT UNSIGNED DEFAULT 0
                           AFTER max_file_size";

    try {
        // Check if column already exists
        $check_column_sql = "SHOW COLUMNS FROM " . TABLE_USERS . " LIKE 'max_disk_quota'";
        $check_statement = $dbh->prepare($check_column_sql);
        $check_statement->execute();

        if ($check_statement->rowCount() == 0) {
            // Column doesn't exist, add it
            $statement = $dbh->prepare($add_quota_column_sql);
            $statement->execute();
        }
    } catch (PDOException $e) {
        error_log("ProjectSend: Could not add max_disk_quota column to users table: " . $e->getMessage());
    }

    // Add default disk quota option for new clients
    add_option_if_not_exists('clients_default_disk_quota', '0');
}
