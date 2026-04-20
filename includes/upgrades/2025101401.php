<?php
function upgrade_2025101401()
{
    global $dbh;

    // Clean up duplicate entries in tbl_role_permissions
    // This fixes the issue where duplicate (role_id, permission) combinations exist

    // First, identify and keep only unique entries
    // We'll use a temporary table approach to remove duplicates

    // Create a temporary table with unique entries
    $query = "CREATE TEMPORARY TABLE tmp_unique_permissions AS
              SELECT MIN(id) as id, role_id, permission, granted
              FROM " . TABLE_ROLE_PERMISSIONS . "
              GROUP BY role_id, permission";
    $statement = $dbh->prepare($query);
    $statement->execute();

    // Get the IDs we want to keep
    $query = "SELECT id FROM tmp_unique_permissions";
    $statement = $dbh->prepare($query);
    $statement->execute();
    $ids_to_keep = $statement->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($ids_to_keep)) {
        // Delete all entries except the ones we want to keep
        $placeholders = implode(',', array_fill(0, count($ids_to_keep), '?'));
        $query = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE id NOT IN ($placeholders)";
        $statement = $dbh->prepare($query);
        $statement->execute($ids_to_keep);
    }

    // Drop the temporary table
    $query = "DROP TEMPORARY TABLE IF EXISTS tmp_unique_permissions";
    $statement = $dbh->prepare($query);
    $statement->execute();
}
