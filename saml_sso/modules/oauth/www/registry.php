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


if (isset($_REQUEST['delete'])) {
	$entryc = $store->get('consumers', $_REQUEST['delete'], '');
	$entry = $entryc['value'];

	requireOwnership($entry, $userid);
	$store->remove('consumers', $entry['key'], '');
}


$list = $store->getList('consumers');

$slist = array('mine' => array(), 'others' => array());
if (is_array($list)) 
foreach($list AS $listitem) {
	if (array_key_exists('owner', $listitem['value'])) {
		if ($listitem['value']['owner'] === $userid) {
			$slist['mine'][] = $listitem; continue;
		}
	}
	$slist['others'][] = $listitem;
}

$template = new SimpleSAML_XHTML_Template($config, 'oauth:registry.list.php');
$template->data['entries'] = $slist;
$template->data['userid'] = $userid;
$template->show();
