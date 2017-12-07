<?php

// Load SimpleSAMLphp, configuration and metadata
$config = SimpleSAML_Configuration::getInstance();
$session = SimpleSAML_Session::getSessionFromRequest();
$oauthconfig = SimpleSAML_Configuration::getOptionalConfig('module_oauth.php');

$store = new sspmod_core_Storage_SQLPermanentStorage('oauth');

$authsource = "admin";	// force admin to authenticate as registry maintainer
$useridattr = $oauthconfig->getValue('useridattr', 'user');

if ($session->isValid($authsource)) {
	$attributes = $session->getAuthData($authsource, 'Attributes');
	// Check if userid exists
	if (!isset($attributes[$useridattr])) 
		throw new Exception('User ID is missing');
	$userid = $attributes[$useridattr][0];
} else {
	$as = SimpleSAML_Auth_Source::getById($authsource);
	$as->initLogin(\SimpleSAML\Utils\HTTP::getSelfURL());
}

function requireOwnership($entry, $userid) {
	if (!isset($entry['owner']))
		throw new Exception('OAuth Consumer has no owner. Which means no one is granted access, not even you.');
	if ($entry['owner'] !== $userid) 
		throw new Exception('OAuth Consumer has an owner that is not equal to your userid, hence you are not granted access.');
}

if (array_key_exists('editkey', $_REQUEST)) {
	$entryc = $store->get('consumers', $_REQUEST['editkey'], '');
	$entry = $entryc['value'];
	requireOwnership($entry, $userid);
	
} else {
	$entry = array(
		'owner' => $userid,
		'key' => SimpleSAML\Utils\Random::generateID(),
		'secret' => SimpleSAML\Utils\Random::generateID(),
	);
}


$editor = new sspmod_oauth_Registry();


if (isset($_POST['submit'])) {
	$editor->checkForm($_POST);

	$entry = $editor->formToMeta($_POST, array(), array('owner' => $userid));

	requireOwnership($entry, $userid);

	$store->set('consumers', $entry['key'], '', $entry);
	
	$template = new SimpleSAML_XHTML_Template($config, 'oauth:registry.saved.php');
	$template->data['entry'] = $entry;
	$template->show();
	exit;
}

$form = $editor->metaToForm($entry);

$template = new SimpleSAML_XHTML_Template($config, 'oauth:registry.edit.tpl.php');
$template->data['form'] = $form;
$template->show();

