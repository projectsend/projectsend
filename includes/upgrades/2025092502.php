<?php
/**
 * Database upgrade: Change created_by column to user_id with foreign key
 * This upgrade modifies the integrations table to use proper foreign key references
 */

function upgrade_2025092502()
{
    global $dbh;

    try {
        // First, update existing records to use user IDs instead of usernames
        // Get all current integrations with created_by usernames
        $query = "SELECT id, created_by FROM " . TABLE_INTEGRATIONS . " WHERE created_by IS NOT NULL";
        $stmt = $dbh->prepare($query);
        $stmt->execute();
        $integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($integrations as $integration) {
            // Find the user ID for this username
            $user_query = "SELECT id FROM " . TABLE_USERS . " WHERE user = :username LIMIT 1";
            $user_stmt = $dbh->prepare($user_query);
            $user_stmt->bindParam(':username', $integration['created_by'], PDO::PARAM_STR);
            $user_stmt->execute();
            $user_id = $user_stmt->fetchColumn();

            if ($user_id) {
                // Update the integration record with the user ID
                $update_query = "UPDATE " . TABLE_INTEGRATIONS . " SET created_by = :user_id WHERE id = :id";
                $update_stmt = $dbh->prepare($update_query);
                $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
                $update_stmt->bindParam(':id', $integration['id'], PDO::PARAM_INT);
                $update_stmt->execute();
            }
        }

        // Now modify the column structure
        // First, rename the column and change its type
        $alter_query = "ALTER TABLE " . TABLE_INTEGRATIONS . "
                        CHANGE COLUMN created_by user_id INT(11) NULL";
        $stmt = $dbh->prepare($alter_query);
        $stmt->execute();

        // Add the foreign key constraint
        $fk_query = "ALTER TABLE " . TABLE_INTEGRATIONS . "
                     ADD CONSTRAINT fk_integrations_user_id
                     FOREIGN KEY (user_id) REFERENCES " . TABLE_USERS . "(id)
                     ON DELETE SET NULL ON UPDATE CASCADE";
        $stmt = $dbh->prepare($fk_query);
        $stmt->execute();

        error_log('Database upgrade 2025092502: Successfully updated integrations table with user_id foreign key');

    } catch (Exception $e) {
        error_log('Database upgrade 2025092502 failed: ' . $e->getMessage());
        throw $e;
    }
}