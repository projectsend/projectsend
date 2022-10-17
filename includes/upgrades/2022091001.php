<?php
function upgrade_2022091001()
{
    $dbh = get_dbh();
    $statement = $dbh->query("ALTER TABLE `" . get_table('members_requests') . "` CHANGE `requested_by` `requested_by` varchar(32) NULL");
}
