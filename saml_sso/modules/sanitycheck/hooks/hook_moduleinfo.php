<?php
/**
 * This hook lets the module describe itself.
 *
 * @param array &$moduleinfo  The links on the frontpage, split into sections.
 */
function sanitycheck_hook_moduleinfo(&$moduleinfo) {
	assert('is_array($moduleinfo)');
	assert('array_key_exists("info", $moduleinfo)');

	$moduleinfo['info']['sanitycheck'] = array(
		'name' => array('en' => 'Sanity check'),
		'description' => array('en' => 'This module adds functionality for other modules to provide santity checks.'),
		
		'dependencies' => array('core'),
		'uses' => array('cron'),
	);

}
