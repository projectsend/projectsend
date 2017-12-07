<?php

/**
 * This script displays a page to the user, which requests that the user
 * authorizes the release of attributes.
 *
 * @package SimpleSAMLphp
 */

SimpleSAML_Logger::info('PreProdWarning - Showing warning to user');

if (!array_key_exists('StateId', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}
$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'warning:request');


if (array_key_exists('yes', $_REQUEST)) {
	// The user has pressed the yes-button

	SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
}



$globalConfig = SimpleSAML_Configuration::getInstance();

$t = new SimpleSAML_XHTML_Template($globalConfig, 'preprodwarning:warning.php');
$t->data['yesTarget'] = SimpleSAML_Module::getModuleURL('preprodwarning/showwarning.php');
$t->data['yesData'] = array('StateId' => $id);
$t->show();
