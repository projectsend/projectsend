<?php
function upgrade_2022110501()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FOLDERS . "` ADD public tinyint(1) NOT NULL default '0' AFTER `name`");
}
