<?php

/**
 * Handle linkback() response from LinkedIn.
 */

if (!array_key_exists('stateid', $_REQUEST)) {
    throw new Exception('Lost OAuth Client State');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['stateid'], sspmod_authlinkedin_Auth_Source_LinkedIn::STAGE_INIT);

// http://developer.linkedin.com/docs/DOC-1008#2_Redirect_the_User_to_our_Authorization_Server
if (array_key_exists('oauth_verifier', $_REQUEST)) {
	$state['authlinkedin:oauth_verifier'] = $_REQUEST['oauth_verifier'];
} else {
	throw new Exception('OAuth verifier not returned.');;
}

// Find authentication source
assert('array_key_exists(sspmod_authlinkedin_Auth_Source_LinkedIn::AUTHID, $state)');
$sourceId = $state[sspmod_authlinkedin_Auth_Source_LinkedIn::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === NULL) {
	throw new Exception('Could not find authentication source with id ' . $sourceId);
}

$source->finalStep($state);

SimpleSAML_Auth_Source::completeAuth($state);

