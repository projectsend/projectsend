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

can_see_content($allowed_levels);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data.csv');

$output = fopen('php://output', 'w');


$log_query	= "SELECT * FROM " . TABLE_LOG . " ORDER BY id DESC";
$log_sql	= $dbh->query( $log_query );
$log_count	= $log_sql->rowCount();

function filter_pipe($input) {
	$output = str_replace('|', '\|', $input);
	return $output;
}

if ($log_count > 0) {
	$log_sql->setFetchMode(PDO::FETCH_ASSOC);
	while ( $log = $log_sql->fetch() ) {
		$render = null;
		$rendered = array();
        $render = format_action_log_record($log);

        if (!empty($render['timestamp'])) { $rendered['timestamp'] = $render['timestamp']; };
		if (!empty($render['part1'])) { $rendered['part1'] = $render['part1']; };
		if (!empty($render['action'])) { $rendered['action'] = $render['action']; };
		if (!empty($render['part2'])) { $rendered['part2'] = $render['part2']; };
		if (!empty($render['part3'])) { $rendered['part3'] = $render['part3']; };
		if (!empty($render['part4'])) { $rendered['part4'] = $render['part4']; };

		fputcsv($output, $rendered);
	}
}

setCookie("log_download_started", 1, time() + 20, '/', "", false, false);
