<?php

/**
 * Handle linkback() response from MySpace.
 */

if (!array_key_exists('stateid', $_REQUEST)) {
	throw new Exception('State Lost - not returned by MySpace Auth');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['stateid'], sspmod_authmyspace_Auth_Source_MySpace::STAGE_INIT);

if (array_key_exists('oauth_problem', $_REQUEST)) {
	// oauth_problem of 'user_refused' means user chose not to login with MySpace
	if (strcmp($_REQUEST['oauth_problem'],'user_refused') == 0) {
		$e = new SimpleSAML_Error_UserAborted();
		SimpleSAML_Auth_State::throwException($state, $e);
	}

	// Error
	$e = new SimpleSAML_Error_Error('Authentication failed: ' . $_REQUEST['oauth_problem']);
	SimpleSAML_Auth_State::throwException($state, $e);
}

if (array_key_exists('oauth_verifier', $_REQUEST)) {
	$state['authmyspace:oauth_verifier'] = $_REQUEST['oauth_verifier'];
} else {
	throw new Exception('OAuth verifier not returned.');;
}

// Find authentication source
assert('array_key_exists(sspmod_authmyspace_Auth_Source_MySpace::AUTHID, $state)');
$sourceId = $state[sspmod_authmyspace_Auth_Source_MySpace::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === NULL) {
	throw new Exception('Could not find authentication source with id ' . $sourceId);
}

$source->finalStep($state);

SimpleSAML_Auth_Source::completeAuth($state);

