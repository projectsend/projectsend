<?php

/**
 * Handler for response from IdP discovery service.
 */

if (!array_key_exists('AuthID', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing AuthID to discovery service response handler');
}

if (!array_key_exists('idpentityid', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing idpentityid to discovery service response handler');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['AuthID'], 'saml:sp:sso');

// Find authentication source
assert('array_key_exists("saml:sp:AuthId", $state)');
$sourceId = $state['saml:sp:AuthId'];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === NULL) {
	throw new Exception('Could not find authentication source with id ' . $sourceId);
}
if (!($source instanceof sspmod_saml_Auth_Source_SP)) {
	throw new SimpleSAML_Error_Exception('Source type changed?');
}

$source->startSSO($_REQUEST['idpentityid'], $state);
