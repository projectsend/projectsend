<?php

/**
 * This script warns a user that his/her certificate is about to expire.
 *
 * @package SimpleSAMLphp
 */

SimpleSAML_Logger::info('AuthX509 - Showing expiry warning to user');

if (!array_key_exists('StateId', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}
$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'warning:expire');


if (array_key_exists('proceed', $_REQUEST)) {
    // The user has pressed the proceed-button
    SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
}

$globalConfig = SimpleSAML_Configuration::getInstance();

$t = new SimpleSAML_XHTML_Template($globalConfig, 'authX509:X509warning.php');
$t->data['target'] = SimpleSAML_Module::getModuleURL('authX509/expirywarning.php');
$t->data['data'] = array('StateId' => $id);
$t->data['daysleft'] = $state['daysleft'];
$t->data['renewurl'] = $state['renewurl'];
$t->show();
