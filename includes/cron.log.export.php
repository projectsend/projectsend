<?php
/**
 * Export the log as a CSV file.
 *
 * @package		ProjectSend
 * @subpackage	Log
 *
 */
/**
 *  Call the required system files
 */
$allowed_levels = array(9);
require_once '../bootstrap.php';

redirect_if_role_not_allowed($allowed_levels);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=cron-log.csv');

$output = fopen('php://output', 'w');


$log_query	= "SELECT * FROM " . TABLE_CRON_LOG . " ORDER BY id DESC";
$log_sql	= $dbh->query( $log_query );
$log_count	= $log_sql->rowCount();

function filter_pipe($input) {
	$output = str_replace('|', '\|', $input);
	return $output;
}

if ($log_count > 0) {
	$log_sql->setFetchMode(PDO::FETCH_ASSOC);
	while ( $log = $log_sql->fetch() ) {
		$rendered = array();

        $rendered['timestamp'] = format_date($log['timestamp']);

        if (!empty($log['sapi'])) { $rendered['sapi'] = $log['sapi']; };
		if (!empty($log['results'])) { $rendered['results'] = $log['results']; };

		fputcsv($output, $rendered);
	}
}

setCookie("log_download_started", 1, time() + 20, '/', "", false, false);
