<?php
/**
 * Show a warning to an user about the SP requesting SSO a short time after
 * doing it previously.
 *
 * @package SimpleSAMLphp
 */

if (!array_key_exists('StateId', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}
$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'core:short_sso_interval');
$session = SimpleSAML_Session::getSessionFromRequest();

if (array_key_exists('continue', $_REQUEST)) {
	// The user has pressed the continue/retry-button
	SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
}

$globalConfig = SimpleSAML_Configuration::getInstance();
$t = new SimpleSAML_XHTML_Template($globalConfig, 'core:short_sso_interval.php');
$t->data['target'] = SimpleSAML_Module::getModuleURL('core/short_sso_interval.php');
$t->data['params'] = array('StateId' => $id);
$t->data['trackId'] = $session->getTrackID();
$t->show();
