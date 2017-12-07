<?php

/**
 * Handle linkback() response from CAS.
 */

if (!isset($_GET['stateID'])) {
	throw new SimpleSAML_Error_BadRequest('Missing stateID parameter.');
}
$state = SimpleSAML_Auth_State::loadState($_GET['stateID'], sspmod_cas_Auth_Source_CAS::STAGE_INIT);

if (!isset($_GET['ticket'])) {
	throw new SimpleSAML_Error_BadRequest('Missing ticket parameter.');
}
$state['cas:ticket'] = (string)$_GET['ticket'];

// Find authentication source
assert('array_key_exists(sspmod_cas_Auth_Source_CAS::AUTHID, $state)');
$sourceId = $state[sspmod_cas_Auth_Source_CAS::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === NULL) {
	throw new Exception('Could not find authentication source with id ' . $sourceId);
}

$source->finalStep($state);


