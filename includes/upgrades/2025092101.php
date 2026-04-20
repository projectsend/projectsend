<?php
/**
 * Add created_by field to custom assets table to track ownership
 * This allows distinguishing between editing own assets vs others' assets
 */
function upgrade_2025092101()
{
    global $dbh;

    // Add created_by column to custom assets table
    $query = "ALTER TABLE " . TABLE_CUSTOM_ASSETS . "
              ADD COLUMN created_by INT(11) DEFAULT NULL AFTER enabled";

    try {
        $statement = $dbh->prepare($query);
        $statement->execute();
    } catch (PDOException $e) {
        // Column might already exist, that's ok
    }

    // Update existing assets to be owned by the first admin user (usually ID 1)
    // This ensures existing assets are not orphaned
    $query = "UPDATE " . TABLE_CUSTOM_ASSETS . "
              SET created_by = 1
              WHERE created_by IS NULL";
    $statement = $dbh->prepare($query);
    $statement->execute();

    // Add new permission for editing others' assets
    // The existing edit_assets permission will now mean "edit others' assets"
    // Users with create_assets can always edit their own
}
?>