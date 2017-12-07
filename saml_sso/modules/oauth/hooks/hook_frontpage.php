<?php
/**
 * Hook to add link to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 */
function oauth_hook_frontpage(&$links) {
	assert('is_array($links)');
	assert('array_key_exists("links", $links)');

	$links['federation']['oauthregistry'] = array(
		'href' => SimpleSAML_Module::getModuleURL('oauth/registry.php'),
		'text' => array('en' => 'OAuth Consumer Registry'),
		'shorttext' => array('en' => 'OAuth Registry'),
	);

}
