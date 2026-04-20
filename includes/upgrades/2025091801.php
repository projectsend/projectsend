<?php
/**
 * Remove old templates.php file as it has been renamed to themes.php
 *
 * @version 2025091801
 */
function upgrade_2025091801()
{
    // Check if the old templates.php file exists in the root directory
    $old_file = ROOT_DIR . DS . 'templates.php';

    if (file_exists($old_file)) {
        // Delete the old templates.php file
        if (@unlink($old_file)) {
            // Log the successful deletion
            global $dbh;
            $logger = new \ProjectSend\Classes\ActionsLog;
            $logger->addEntry([
                'action' => 50,
                'owner_id' => 1,
                'details' => [
                    'file_removed' => 'templates.php',
                    'reason' => 'Renamed to themes.php',
                ],
            ]);
        }
    }

    // Also update any references in the database if needed
    // For example, if there were any options or menu items pointing to templates.php
    global $dbh;

    // Update any saved navigation preferences that might reference the old file
    $query = "UPDATE " . TABLE_OPTIONS . "
              SET value = REPLACE(value, 'templates.php', 'themes.php')
              WHERE name LIKE '%nav%' OR name LIKE '%menu%'";
    $statement = $dbh->prepare($query);
    $statement->execute();
}