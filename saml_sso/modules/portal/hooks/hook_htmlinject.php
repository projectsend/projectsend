<?php

/**
 * Hook to inject HTML content into all pages...
 *
 * @param array &$hookinfo  hookinfo
 */
function portal_hook_htmlinject(&$hookinfo) {
	assert('is_array($hookinfo)');
	assert('array_key_exists("pre", $hookinfo)');
	assert('array_key_exists("post", $hookinfo)');
	assert('array_key_exists("page", $hookinfo)');

	$links = array('links' => array());
	SimpleSAML_Module::callHooks('frontpage', $links);

	$portalConfig = SimpleSAML_Configuration::getOptionalConfig('module_portal.php');
	
	$allLinks = array();
	foreach($links AS $ls) {
		$allLinks = array_merge($allLinks, $ls);
	}

	$pagesets = $portalConfig->getValue('pagesets', array(
		array('frontpage_welcome', 'frontpage_config', 'frontpage_auth', 'frontpage_federation'),
	));
	SimpleSAML_Module::callHooks('portalextras', $pagesets);
	$portal = new sspmod_portal_Portal($allLinks, $pagesets);
	
	if (!$portal->isPortalized($hookinfo['page'])) return;

	// Include jquery UI CSS files in header
	$hookinfo['jquery']['css'] = TRUE;

	// Header
	$hookinfo['pre'][]  = '<div id="portalmenu" class="ui-tabs ui-widget ui-widget-content ui-corner-all">' . 
		$portal->getMenu($hookinfo['page']) . 
		'<div id="portalcontent" class="ui-tabs-panel ui-widget-content ui-corner-bottom">';

	// Footer
	$hookinfo['post'][] = '</div></div>';
	
}
