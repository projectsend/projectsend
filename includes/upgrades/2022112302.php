<?php
function upgrade_2022112302()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `disk_folder_year` int(4) NULL AFTER `folder_id`");
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `disk_folder_month` int(4) NULL AFTER `disk_folder_year`");
}
