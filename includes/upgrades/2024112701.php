<?php

function upgrade_2024112701()
{
    global $dbh;
    $query = "UPDATE " . TABLE_FOLDERS . " set public = 1";
    $statement = $dbh->prepare($query);
    $statement->execute();
}
