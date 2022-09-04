<?php
function upgrade_2022071801()
{
    global $dbh;
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `encrypted_browser` int(1) DEFAULT '0' AFTER `public_token`");
    $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `encrypted_server` int(1) DEFAULT '0' AFTER `encrypted_browser`");
}
