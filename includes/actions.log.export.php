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
require_once('../sys.includes.php');

if(!check_for_admin()) {
    return;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data.csv');

$output = fopen('php://output', 'w');


$log_query	= "SELECT * FROM " . TABLE_LOG . " ORDER BY id DESC";
$log_sql	= $dbh->query( $log_query );
$log_count	= $log_sql->rowCount();

if ($log_count > 0) {
	$log_sql->setFetchMode(PDO::FETCH_ASSOC);
	while ( $log = $log_sql->fetch() ) {
		$render = '';
		$rendered = array();
		$render = render_log_action(
							array(
								'action'				=> $log['action'],
								'timestamp'				=> $log['timestamp'],
								'owner_id'				=> $log['owner_id'],
								'owner_user'			=> $log['owner_user'],
								'affected_file'			=> $log['affected_file'],
								'affected_file_name'	=> $log['affected_file_name'],
								'affected_account'		=> $log['affected_account'],
								'affected_account_name'	=> $log['affected_account_name']
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
?>