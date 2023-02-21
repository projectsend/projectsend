<?php

$allowed_levels = array(9, 8, 7, 0);
require_once 'bootstrap.php';

$link = htmlentities($_GET['link']);
if (!$link) {
    exit_with_error_code(400);
}

/**
 * @var PDO $dbh
 */
global $dbh;
$statement = $dbh->prepare("SELECT * FROM `" . TABLE_CUSTOM_DOWNLOADS . "` WHERE link=:link");
$statement->bindParam(':link', $link);
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);

if ($statement->rowCount() == 0) {
    exit_with_error_code(404);
}

while ($row = $statement->fetch()) {
    $link = html_output($row['link']);
    $file_id = html_output($row['file_id']);
    $client_id = html_output($row['client_id']);
    $timestamp = html_output($row['timestamp']);
    $expiry_date = html_output($row['expiry_date']);
    $visit_count = html_output($row['visit_count']);
}

if (!is_null($expiry_date) && $expiry_date <= (new DateTime())->getTimestamp()) {
    // link expired
    exit_with_error_code(410);
}

if (!$file_id) {
    exit_with_error_code(404);
}

$file = new \ProjectSend\Classes\Files($file_id);

$statement = $dbh->prepare("UPDATE " . TABLE_CUSTOM_DOWNLOADS . " SET visit_count=:visit_count WHERE link=:link");
$visit_count++;
$statement->bindParam(':visit_count', $visit_count, PDO::PARAM_INT);
$statement->bindParam(':link', $link);
$statement->execute();

ps_redirect(BASE_URI . "download.php?id={$file->id}&token={$file->public_token}");
