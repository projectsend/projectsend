<?php

/**
 * about2expire.php
 *
 * @package SimpleSAMLphp
 */

SimpleSAML_Logger::info('expirycheck - User has been warned that NetID is near to expirational date.');

if (!array_key_exists('StateId', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}
$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'expirywarning:about2expire');

if (array_key_exists('yes', $_REQUEST)) {
	// The user has pressed the yes-button
	SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
}

$globalConfig = SimpleSAML_Configuration::getInstance();

$t = new SimpleSAML_XHTML_Template($globalConfig, 'expirycheck:about2expire.php');
$t->data['yesTarget'] = SimpleSAML_Module::getModuleURL('expirycheck/about2expire.php');
$t->data['yesData'] = array('StateId' => $id);
$t->data['daysleft'] = $state['daysleft'];
$t->data['expireOnDate'] = $state['expireOnDate'];
$t->data['netId'] = $state['netId'];
$t->show();
