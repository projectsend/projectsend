<?php
/**
 * Complete removal of leftover role_level column from role_permissions table
 *
 * Migration 2025092004 was supposed to drop role_level and re-key the table
 * to (role_id, permission), but the ALTER TABLE was wrapped in a try/catch
 * that silently swallowed failures. On upgrades from older versions (e.g. r14xx),
 * the column and old unique key can survive, causing:
 *
 *   SQLSTATE[HY000]: General error: 1364
 *   Field 'role_level' doesn't have a default value
 *
 * when the application inserts permissions using only (role_id, permission, granted).
 *
 * This migration is idempotent: it checks for the column before acting.
 *
 * @see https://github.com/projectsend/projectsend/issues/1527
 */
function upgrade_2025101501()
{
    global $dbh;

    // Check if role_level column still exists
    $check_sql = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_level'";
    $stmt = $dbh->prepare($check_sql);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Column already removed — nothing to do
        return;
    }

    // Step 1: Ensure role_id column exists (should already from 2025092004)
    $check_role_id = "SHOW COLUMNS FROM " . TABLE_ROLE_PERMISSIONS . " LIKE 'role_id'";
    $stmt = $dbh->prepare($check_role_id);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Add role_id column if missing
        $dbh->exec("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " ADD COLUMN role_id int(11) DEFAULT NULL AFTER role_level");

        // Migrate role_level values to role_id using the roles table
        $role_map_sql = "SELECT id, name FROM " . TABLE_ROLES;
        $roles_stmt = $dbh->query($role_map_sql);
        $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build level-to-id map from known system role names
        $level_map = [
            'Client' => 0,
            'Uploader' => 7,
            'Account Manager' => 8,
            'System Administrator' => 9,
        ];

        foreach ($roles as $role) {
            if (isset($level_map[$role['name']])) {
                $update_sql = "UPDATE " . TABLE_ROLE_PERMISSIONS . "
                               SET role_id = :role_id
                               WHERE role_level = :role_level AND (role_id IS NULL OR role_id = 0)";
                $update_stmt = $dbh->prepare($update_sql);
                $update_stmt->execute([
                    'role_id' => $role['id'],
                    'role_level' => $level_map[$role['name']],
                ]);
            }
        }
    }

    // Step 2: Delete orphan rows where role_id is still NULL (no matching role)
    $dbh->exec("DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE role_id IS NULL");

    // Step 3: Remove duplicate (role_id, permission) rows keeping the lowest id
    $dedup_sql = "DELETE t1 FROM " . TABLE_ROLE_PERMISSIONS . " t1
                  INNER JOIN " . TABLE_ROLE_PERMISSIONS . " t2
                  ON t1.role_id = t2.role_id
                  AND t1.permission = t2.permission
                  AND t1.id > t2.id";
    $dbh->exec($dedup_sql);

    // Step 4: Drop old indexes that reference role_level
    $indexes = $dbh->query("SHOW INDEX FROM " . TABLE_ROLE_PERMISSIONS)->fetchAll(PDO::FETCH_ASSOC);
    $index_names = array_unique(array_column($indexes, 'Key_name'));

    foreach ($index_names as $index_name) {
        if ($index_name === 'PRIMARY') {
            continue;
        }

        // Check if this index involves role_level
        $index_columns = array_filter($indexes, function ($idx) use ($index_name) {
            return $idx['Key_name'] === $index_name;
        });
        $column_names = array_column($index_columns, 'Column_name');

        if (in_array('role_level', $column_names)) {
            try {
                $dbh->exec("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " DROP INDEX `{$index_name}`");
            } catch (PDOException $e) {
                error_log("ProjectSend upgrade 2025101501: Could not drop index {$index_name}: " . $e->getMessage());
            }
        }
    }

    // Step 5: Drop the role_level column
    try {
        $dbh->exec("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " DROP COLUMN role_level");
    } catch (PDOException $e) {
        error_log("ProjectSend upgrade 2025101501: Could not drop role_level column: " . $e->getMessage());
        throw $e; // Re-throw — this must succeed for the app to work
    }

    // Step 6: Ensure the correct unique key exists on (role_id, permission)
    $has_unique = false;
    $refreshed_indexes = $dbh->query("SHOW INDEX FROM " . TABLE_ROLE_PERMISSIONS)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($refreshed_indexes as $idx) {
        if ($idx['Column_name'] === 'role_id' && $idx['Non_unique'] == 0) {
            $has_unique = true;
            break;
        }
    }

    if (!$has_unique) {
        try {
            $dbh->exec("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " ADD UNIQUE KEY role_permission (role_id, permission)");
        } catch (PDOException $e) {
            error_log("ProjectSend upgrade 2025101501: Could not add unique key: " . $e->getMessage());
        }
    }

    error_log("ProjectSend upgrade 2025101501: Successfully removed leftover role_level column from role_permissions");
}
