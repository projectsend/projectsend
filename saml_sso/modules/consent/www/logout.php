<?php
/**
 * This is the handler for logout started from the consent page.
 *
 * @package SimpleSAMLphp
 */

if (!array_key_exists('StateId', $_GET)) {
    throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}
$state = SimpleSAML_Auth_State::loadState($_GET['StateId'], 'consent:request');

$state['Responder'] = array('sspmod_consent_Logout', 'postLogout');

$idp = SimpleSAML_IdP::getByState($state);
$idp->handleLogoutRequest($state, NULL);
assert('FALSE');
