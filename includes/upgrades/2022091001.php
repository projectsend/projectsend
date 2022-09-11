<?php
function upgrade_2022091001()
{
    global $dbh;
    $statement = $dbh->query("ALTER TABLE `" . TABLE_MEMBERS_REQUESTS . "` CHANGE `requested_by` `requested_by` varchar(32) NULL");
}
