<?php
/**
 * Hook to add the modinfo module to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 */
function statistics_hook_frontpage(&$links) {
	assert('is_array($links)');
	assert('array_key_exists("links", $links)');

	$links['config']['statistics'] = array(
		'href' => SimpleSAML_Module::getModuleURL('statistics/showstats.php'),
		'text' => array('en' => 'Show statistics', 'no' => 'Vis statistikk'),
		'shorttext' => array('en' => 'Statistics', 'no' => 'Statistikk'),
	);
	$links['config']['statisticsmeta'] = array(
		'href' => SimpleSAML_Module::getModuleURL('statistics/statmeta.php'),
		'text' => array('en' => 'Show statistics metadata', 'no' => 'Vis statistikk metadata'),
		'shorttext' => array('en' => 'Statistics metadata', 'no' => 'Statistikk metadata'),
	);

}
