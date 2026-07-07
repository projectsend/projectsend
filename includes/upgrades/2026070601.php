<?php
/**
 * Finish the role_level -> role_id migration on tbl_role_permissions.
 *
 * Upgrade 2025092004 tried to drop the legacy role_level column and its
 * UNIQUE KEY role_permission (role_level, permission) in a single combined
 * ALTER TABLE, but swallowed failures silently. Upgrade 2025092005 then
 * unconditionally marked that cleanup as "completed" without verifying it.
 * On installs where the ALTER failed, role_level stayed NOT NULL and any
 * new permission insert defaulted it to 0, silently colliding with leftover
 * legacy rows (role_id NULL) for the same permission key - the cause of
 * permissions being dropped without error on custom roles (GH #1597).
 */
function upgrade_2026070601()
{
    global $dbh;

    $check_column_sql = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_level'";
    $statement = $dbh->prepare($check_column_sql);
    $statement->execute();
    $has_role_level = $statement->rowCount() > 0;

    if ($has_role_level) {
        // Drop the legacy index before the column, since it's what actually
        // blocks new inserts once a colliding row exists.
        $check_index_sql = "SHOW INDEX FROM " . TABLE_ROLE_PERMISSIONS . " WHERE Key_name = 'role_permission'";
        $statement = $dbh->prepare($check_index_sql);
        $statement->execute();

        if ($statement->rowCount() > 0) {
            try {
                $dbh->prepare("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " DROP INDEX role_permission")->execute();
            } catch (PDOException $e) {
                error_log("ProjectSend upgrade 2026070601: could not drop legacy role_permission index: " . $e->getMessage());
            }
        }

        // Rows with role_id NULL are leftovers from the pre-custom-roles
        // system and can never belong to a real role - only exist to collide.
        $dbh->prepare("DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE role_id IS NULL")->execute();

        try {
            $dbh->prepare("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " DROP COLUMN role_level")->execute();
        } catch (PDOException $e) {
            error_log("ProjectSend upgrade 2026070601: could not drop role_level column: " . $e->getMessage());
        }
    }

    // Duplicate (role_id, permission) rows could have piled up while the
    // legacy index above was blocking legitimate inserts. Clear them before
    // adding the correct unique key.
    $dbh->prepare("CREATE TEMPORARY TABLE tmp_role_permissions_dupes AS
                    SELECT MIN(id) as keep_id, role_id, permission
                    FROM " . TABLE_ROLE_PERMISSIONS . "
                    WHERE role_id IS NOT NULL
                    GROUP BY role_id, permission
                    HAVING COUNT(*) > 1")->execute();

    $statement = $dbh->prepare("SELECT rp.id FROM " . TABLE_ROLE_PERMISSIONS . " rp
                    INNER JOIN tmp_role_permissions_dupes t
                        ON rp.role_id = t.role_id AND rp.permission = t.permission
                    WHERE rp.id != t.keep_id");
    $statement->execute();
    $duplicate_ids = $statement->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($duplicate_ids)) {
        $placeholders = implode(',', array_fill(0, count($duplicate_ids), '?'));
        $dbh->prepare("DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE id IN ($placeholders)")->execute($duplicate_ids);
    }

    $dbh->prepare("DROP TEMPORARY TABLE IF EXISTS tmp_role_permissions_dupes")->execute();

    // Make sure the correct unique key on (role_id, permission) is in place.
    $check_new_index_sql = "SHOW INDEX FROM " . TABLE_ROLE_PERMISSIONS . " WHERE Key_name = 'role_permission'";
    $statement = $dbh->prepare($check_new_index_sql);
    $statement->execute();

    if ($statement->rowCount() == 0) {
        try {
            $dbh->prepare("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " ADD UNIQUE KEY role_permission (role_id, permission)")->execute();
        } catch (PDOException $e) {
            error_log("ProjectSend upgrade 2026070601: could not add role_id/permission unique key: " . $e->getMessage());
        }
    }
}
