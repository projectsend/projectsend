<?php
function upgrade_2022102902()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` ADD COLUMN `user_id` int(11) NULL AFTER `name`");
    $dbh->query("ALTER TABLE " . TABLE_FOLDERS . " ADD FOREIGN KEY (`user_id`) REFERENCES " . TABLE_USERS . "(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
}
