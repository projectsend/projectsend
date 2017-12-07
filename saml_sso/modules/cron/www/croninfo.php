<?php

/**
 * The _include script registers a autoloader for the SimpleSAMLphp libraries. It also
 * initializes the SimpleSAMLphp config class with the correct path.
 */
require_once('_include.php');


// Load SimpleSAMLphp, configuration and metadata
$config = SimpleSAML_Configuration::getInstance();
$session = SimpleSAML_Session::getSessionFromRequest();

SimpleSAML\Utils\Auth::requireAdmin();

$cronconfig = SimpleSAML_Configuration::getConfig('module_cron.php');

$key = $cronconfig->getValue('key', '');
$tags = $cronconfig->getValue('allowed_tags');

$def = array(
	'weekly' 	=> "22 0 * * 0",
	'daily' 	=> "02 0 * * *",
	'hourly'	=> "01 * * * *",
	'default' 	=> "XXXXXXXXXX",
);

$urls = array();
foreach ($tags AS $tag) {
	$urls[] = array(
		'href' => SimpleSAML_Module::getModuleURL('cron/cron.php', array('key' => $key, 'tag' => $tag)),
		'tag' => $tag,
		'int' => (array_key_exists($tag, $def) ? $def[$tag] : $def['default']),
	);
}



$t = new SimpleSAML_XHTML_Template($config, 'cron:croninfo-tpl.php', 'cron:cron');
$t->data['urls'] = $urls;
$t->show();
