<?php
function upgrade_2022102701()
{
    global $dbh;

    try { $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` ADD COLUMN `uuid` varchar(32) NOT NULL AFTER `id`"); } catch (\Exception $e) {}
    try { $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` ADD COLUMN `slug` varchar(32) NOT NULL AFTER `name`"); } catch (\Exception $e) {}

    // Drop foreign keys on client_id and group_id by looking up actual constraint names
    $fks_to_drop = [];
    try {
        $stmt = $dbh->prepare("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME IN ('client_id', 'group_id')
            AND REFERENCED_TABLE_NAME IS NOT NULL");
        $stmt->execute([':table' => TABLE_FOLDERS]);
        $fks_to_drop = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    } catch (\Exception $e) {}

    foreach ($fks_to_drop as $fk_name) {
        try { $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` DROP FOREIGN KEY `" . $fk_name . "`"); } catch (\Exception $e) {}
    }

    try { $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` DROP COLUMN `client_id`"); } catch (\Exception $e) {}
    try { $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` DROP COLUMN `group_id`"); } catch (\Exception $e) {}
}
