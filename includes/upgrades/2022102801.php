<?php
function upgrade_2022102801()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `folder_id` int(11) NULL AFTER `public_token`");
    $dbh->query("ALTER TABLE " . TABLE_FILES . " ADD FOREIGN KEY (`folder_id`) REFERENCES " . TABLE_FOLDERS . "(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
}
