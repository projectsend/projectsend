<?php
/**
 * Hook to run a cron job.
 *
 * @param array &$croninfo  Output
 */
function sanitycheck_hook_cron(&$croninfo) {
	assert('is_array($croninfo)');
	assert('array_key_exists("summary", $croninfo)');
	assert('array_key_exists("tag", $croninfo)');

	SimpleSAML_Logger::info('cron [sanitycheck]: Running cron in cron tag [' . $croninfo['tag'] . '] ');

	try {
	
		$sconfig = SimpleSAML_Configuration::getOptionalConfig('config-sanitycheck.php');

		$cronTag = $sconfig->getString('cron_tag', NULL);
		if ($cronTag === NULL || $cronTag !== $croninfo['tag']) {
			return;
		}

		$info = array();
		$errors = array();
		$hookinfo = array(
			'info' => &$info,
			'errors' => &$errors,
		);
		
		SimpleSAML_Module::callHooks('sanitycheck', $hookinfo);
		
		if (count($errors) > 0) {
			foreach ($errors AS $err) {
				$croninfo['summary'][] = 'Sanitycheck error: ' . $err;
			}
		}
		
	} catch (Exception $e) {
		$croninfo['summary'][] = 'Error executing sanity check: ' . $e->getMessage();
	}

}
