<?php
function upgrade_2022102701()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` ADD COLUMN `uuid` varchar(32) NOT NULL AFTER `id`");
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` ADD COLUMN `slug` varchar(32) NOT NULL AFTER `name`");
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` DROP FOREIGN KEY `tbl_folders_ibfk_2`");
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` DROP FOREIGN KEY `tbl_folders_ibfk_3`");
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` DROP COLUMN `client_id`");
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` DROP COLUMN `group_id`");
}
