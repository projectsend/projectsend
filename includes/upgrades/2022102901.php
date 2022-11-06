<?php
function upgrade_2022102901()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` MODIFY COLUMN `name` varchar(100) NOT NULL");
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` MODIFY COLUMN `slug` varchar(100) NOT NULL");
}
