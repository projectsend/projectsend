<?php
function upgrade_2022072101()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `size_encrypted` varchar(50) DEFAULT NULL AFTER `encrypted_server`");
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `size_unencrypted` varchar(50) DEFAULT NULL AFTER `size_encrypted`");
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `sha256` varchar(50) DEFAULT NULL AFTER `size_unencrypted`");
}
