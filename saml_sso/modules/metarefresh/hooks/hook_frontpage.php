<?php
/**
 * Hook to add links to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 */
function metarefresh_hook_frontpage(&$links) {
	assert('is_array($links)');
	assert('array_key_exists("links", $links)');

	$links['federation'][] = array(
		'href' => SimpleSAML_Module::getModuleURL('metarefresh/fetch.php'),
		'text' => array('en' => 'Metarefresh: fetch metadata'),
	);

}
