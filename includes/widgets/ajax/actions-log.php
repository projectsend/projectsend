<?php
require_once '../../../bootstrap.php';

if (!defined('CURRENT_USER_LEVEL') or CURRENT_USER_LEVEL != 9) {
    ps_redirect(BASE_URI);
}

$dbh = get_dbh();
$log_query = "SELECT * FROM " . get_table('actions_log');
if (isset($_GET['action']) && is_numeric($_GET['action'])) {
    $log_query .= " WHERE action = :action";
    $params[':action'] = $_GET['action'];
}
$log_query .= " ORDER BY id DESC LIMIT :max";
$params[':max'] = 20;

$return = [
    'actions' => [],
];
$sql_log = $dbh->prepare( $log_query );
$sql_log->execute( $params );
if ( $sql_log->rowCount() > 0 ) {
    $sql_log->setFetchMode(PDO::FETCH_ASSOC);
    while ( $row = $sql_log->fetch() ) {
        $return['actions'][] = format_action_log_record($row);
    }
}

echo json_encode($return);
exit;