<?php
/**
 * Hook to run a cron job.
 *
 * @param array &$croninfo  Output
 */
function cron_hook_cron(&$croninfo) {
	assert('is_array($croninfo)');
	assert('array_key_exists("summary", $croninfo)');
	assert('array_key_exists("tag", $croninfo)');

	$cronconfig = SimpleSAML_Configuration::getConfig('module_cron.php');
	
	if ($cronconfig->getValue('debug_message', TRUE)) {

		$croninfo['summary'][] = 'Cron did run tag [' . $croninfo['tag'] . '] at ' . date(DATE_RFC822);
	}

}
