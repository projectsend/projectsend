<?php

/**
 * Handle linkback() response from Twitter.
 */

if (!array_key_exists('AuthState', $_REQUEST) || empty($_REQUEST['AuthState'])) {
	throw new SimpleSAML_Error_BadRequest('Missing state parameter on twitter linkback endpoint.');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['AuthState'], sspmod_authtwitter_Auth_Source_Twitter::STAGE_INIT);

// Find authentication source
if (!array_key_exists(sspmod_authtwitter_Auth_Source_Twitter::AUTHID, $state)) {
	throw new SimpleSAML_Error_BadRequest('No data in state for ' . sspmod_authtwitter_Auth_Source_Twitter::AUTHID);
}
$sourceId = $state[sspmod_authtwitter_Auth_Source_Twitter::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === NULL) {
	throw new SimpleSAML_Error_BadRequest('Could not find authentication source with id ' . var_export($sourceId, TRUE));
}

try {
	if (array_key_exists('denied', $_REQUEST)) {
		throw new SimpleSAML_Error_UserAborted();
	}

	$source->finalStep($state);
} catch (SimpleSAML_Error_Exception $e) {
	SimpleSAML_Auth_State::throwException($state, $e);
} catch (Exception $e) {
	SimpleSAML_Auth_State::throwException($state, new SimpleSAML_Error_AuthSource($sourceId, 'Error on authtwitter linkback endpoint.', $e));
}

SimpleSAML_Auth_Source::completeAuth($state);
