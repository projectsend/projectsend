<?php

/**
 * Handle linkback() response from Facebook.
 */
 
if (!array_key_exists('AuthState', $_REQUEST) || empty($_REQUEST['AuthState'])) {
	throw new SimpleSAML_Error_BadRequest('Missing state parameter on facebook linkback endpoint.');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['AuthState'], sspmod_authfacebook_Auth_Source_Facebook::STAGE_INIT);

// Find authentication source
if (!array_key_exists(sspmod_authfacebook_Auth_Source_Facebook::AUTHID, $state)) {
	throw new SimpleSAML_Error_BadRequest('No data in state for ' . sspmod_authfacebook_Auth_Source_Facebook::AUTHID);
}
$sourceId = $state[sspmod_authfacebook_Auth_Source_Facebook::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === NULL) {
	throw new SimpleSAML_Error_BadRequest('Could not find authentication source with id ' . var_export($sourceId, TRUE));
}

try {
	if (isset($_REQUEST['error_reason']) && $_REQUEST['error_reason'] == 'user_denied') {
		throw new SimpleSAML_Error_UserAborted();
	}

	$source->finalStep($state);
} catch (SimpleSAML_Error_Exception $e) {
	SimpleSAML_Auth_State::throwException($state, $e);
} catch (Exception $e) {
	SimpleSAML_Auth_State::throwException($state, new SimpleSAML_Error_AuthSource($sourceId, 'Error on facebook linkback endpoint.', $e));
}

SimpleSAML_Auth_Source::completeAuth($state);
