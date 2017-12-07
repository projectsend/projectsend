<?php
/**
 * Hook to add the modinfo module to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 */
function sanitycheck_hook_frontpage(&$links) {
	assert('is_array($links)');
	assert('array_key_exists("links", $links)');

	$links['config']['santitycheck'] = array(
		'href' => SimpleSAML_Module::getModuleURL('sanitycheck/index.php'),
		'text' => array('en' => 'Sanity check of your SimpleSAMLphp setup'),
		'shorttext' => array('en' => 'SanityCheck'),
	);

}
