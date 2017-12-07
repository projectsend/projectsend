<?php

/**
 * Handle linkback() response from Windows Live ID.
 */

if (!array_key_exists('state', $_REQUEST)) {
    throw new Exception('Lost OAuth Client State');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['state'], sspmod_authwindowslive_Auth_Source_LiveID::STAGE_INIT);

// http://msdn.microsoft.com/en-us/library/ff749771.aspx
if (array_key_exists('code', $_REQUEST)) {
    // good
    $state['authwindowslive:verification_code'] = $_REQUEST['code'];

    if (array_key_exists('exp', $_REQUEST)) {
        $state['authwindowslive:exp'] = $_REQUEST['exp'];
    }
} else {
    // In the OAuth WRAP service, error_reason = 'user_denied' means user chose
    // not to login with LiveID. It isn't clear that this is still true in the
    // newer API, but the parameter name has changed to error. It doesn't hurt
    // to preserve support for this, so this is left in as a placeholder.
    // redirect them to their original page so they can choose another auth mechanism
    if ($_REQUEST['error'] === 'user_denied') {
        $e = new SimpleSAML_Error_UserAborted();
        SimpleSAML_Auth_State::throwException($state, $e);
    }

    // error
    throw new Exception('Authentication failed: ['.$_REQUEST['error'].'] '.$_REQUEST['error_description']);
}

// find authentication source
assert('array_key_exists(sspmod_authwindowslive_Auth_Source_LiveID::AUTHID, $state)');
$sourceId = $state[sspmod_authwindowslive_Auth_Source_LiveID::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === null) {
    throw new Exception('Could not find authentication source with id '.$sourceId);
}

$source->finalStep($state);

SimpleSAML_Auth_Source::completeAuth($state);
