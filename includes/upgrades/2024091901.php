<?php

function upgrade_2024091901()
{
    global $dbh;
    $dbh->query(
        "ALTER TABLE " . TABLE_CUSTOM_DOWNLOADS . " ADD COLUMN `id` INT AUTO_INCREMENT UNIQUE FIRST;"
    );
}
