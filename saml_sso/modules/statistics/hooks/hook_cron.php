<?php
/**
 * Hook to run a cron job.
 *
 * @param array &$croninfo  Output
 */
function statistics_hook_cron(&$croninfo) {
	assert('is_array($croninfo)');
	assert('array_key_exists("summary", $croninfo)');
	assert('array_key_exists("tag", $croninfo)');

	$statconfig = SimpleSAML_Configuration::getConfig('module_statistics.php');
	
	if (is_null($statconfig->getValue('cron_tag', NULL))) return;
	if ($statconfig->getValue('cron_tag', NULL) !== $croninfo['tag']) return;
	
	$maxtime = $statconfig->getInteger('time_limit', NULL);
	if($maxtime){
		set_time_limit($maxtime);
	}
	
	try {
		$aggregator = new sspmod_statistics_Aggregator();
		$results = $aggregator->aggregate();
		if (empty($results)) {
			SimpleSAML_Logger::notice('Output from statistics aggregator was empty.');
		} else {
			$aggregator->store($results);
		}
	} catch (Exception $e) {
		$message = 'Loganalyzer threw exception: ' . $e->getMessage();
		SimpleSAML_Logger::warning($message);
		$croninfo['summary'][] = $message;
	}
}
