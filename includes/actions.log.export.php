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
		$render = '';
		$rendered = array();

        $render = render_log_action(
							array(
								'action' => filter_pipe($log['action']),
								'timestamp' => filter_pipe($log['timestamp']),
								'owner_id' => filter_pipe($log['owner_id']),
								'owner_user' => filter_pipe($log['owner_user']),
								'affected_file' => filter_pipe($log['affected_file']),
								'affected_file_name' => filter_pipe($log['affected_file_name']),
								'affected_account' => filter_pipe($log['affected_account']),
								'affected_account_name'	=> filter_pipe($log['affected_account_name'])
							)
		);

        if (!empty($render['timestamp'])) { $rendered['timestamp'] = $render['timestamp']; };
		if (!empty($render['1'])) { $rendered['1'] = $render['1']; };
		if (!empty($render['text'])) { $rendered['text'] = $render['text']; };
		if (!empty($render['2'])) { $rendered['2'] = $render['2']; };
		if (!empty($render['3'])) { $rendered['3'] = $render['3']; };
		if (!empty($render['4'])) { $rendered['4'] = $render['4']; };

		fputcsv($output, $rendered);
	}
}

setCookie("log_download_started", 1, time() + 20, '/', "", false, false);
