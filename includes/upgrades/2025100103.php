<?php
/**
 * Database upgrade: Fix view_downloads_details permission category
 * Updates category from 'Files' to 'files' for existing installations
 */

function upgrade_2025100103()
{
    global $dbh;

    try {
        // Fix permission category to lowercase
        $update_query = "UPDATE " . TABLE_PERMISSIONS . "
                        SET category = 'files'
                        WHERE permission_key = 'view_downloads_details' AND category = 'Files'";
        $update_stmt = $dbh->prepare($update_query);
        $update_stmt->execute();

        error_log('Database upgrade 2025100103: Fixed view_downloads_details permission category');

    } catch (Exception $e) {
        error_log('Database upgrade 2025100103 failed: ' . $e->getMessage());
        throw $e;
    }
}
