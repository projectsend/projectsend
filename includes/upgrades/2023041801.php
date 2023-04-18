<?php

function upgrade_2023041801()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_DOWNLOADS . "` CHANGE `remote_ip` `remote_ip` varchar(100) NULL");
}
