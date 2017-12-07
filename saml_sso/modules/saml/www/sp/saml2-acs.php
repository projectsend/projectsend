<?php

/**
 * Assertion consumer service handler for SAML 2.0 SP authentication client.
 */

if (!array_key_exists('PATH_INFO', $_SERVER)) {
    throw new SimpleSAML_Error_BadRequest('Missing authentication source ID in assertion consumer service URL');
}

$sourceId = substr($_SERVER['PATH_INFO'], 1);
$source = SimpleSAML_Auth_Source::getById($sourceId, 'sspmod_saml_Auth_Source_SP');
$spMetadata = $source->getMetadata();

try {
    $b = SAML2_Binding::getCurrentBinding();
} catch (Exception $e) { // TODO: look for a specific exception
    // This is dirty. Instead of checking the message of the exception, SAML2_Binding::getCurrentBinding() should throw
    // an specific exception when the binding is unknown, and we should capture that here
    if ($e->getMessage() === 'Unable to find the current binding.') {
        throw new SimpleSAML_Error_Error('ACSPARAMS', $e, 400);
    } else {
        throw $e; // do not ignore other exceptions!
    }
}

if ($b instanceof SAML2_HTTPArtifact) {
    $b->setSPMetadata($spMetadata);
}

$response = $b->receive();
if (!($response instanceof SAML2_Response)) {
    throw new SimpleSAML_Error_BadRequest('Invalid message received to AssertionConsumerService endpoint.');
}

$idp = $response->getIssuer();
if ($idp === null) {
    // no Issuer in the response. Look for an unencrypted assertion with an issuer
    foreach ($response->getAssertions() as $a) {
        if ($a instanceof SAML2_Assertion) {
            // we found an unencrypted assertion, there should be an issuer here
            $idp = $a->getIssuer();
            break;
        }
    }
    if ($idp === null) {
        // no issuer found in the assertions
        throw new Exception('Missing <saml:Issuer> in message delivered to AssertionConsumerService.');
    }
}

$session = SimpleSAML_Session::getSessionFromRequest();
$prevAuth = $session->getAuthData($sourceId, 'saml:sp:prevAuth');
if ($prevAuth !== null && $prevAuth['id'] === $response->getId() && $prevAuth['issuer'] === $idp) {
    /* OK, it looks like this message has the same issuer
     * and ID as the SP session we already have active. We
     * therefore assume that the user has somehow triggered
     * a resend of the message.
     * In that case we may as well just redo the previous redirect
     * instead of displaying a confusing error message.
     */
    SimpleSAML_Logger::info(
        'Duplicate SAML 2 response detected - ignoring the response and redirecting the user to the correct page.'
    );
    if (isset($prevAuth['redirect'])) {
        \SimpleSAML\Utils\HTTP::redirectTrustedURL($prevAuth['redirect']);
    }

    SimpleSAML_Logger::info('No RelayState or ReturnURL available, cannot redirect.');
    throw new SimpleSAML_Error_Exception('Duplicate assertion received.');
}

$idpMetadata = array();

$state = null;
$stateId = $response->getInResponseTo();
if (!empty($stateId)) {
    // this should be a response to a request we sent earlier
    try {
        $state = SimpleSAML_Auth_State::loadState($stateId, 'saml:sp:sso');
    } catch (Exception $e) {
        // something went wrong,
        SimpleSAML_Logger::warning('Could not load state specified by InResponseTo: '.$e->getMessage().
            ' Processing response as unsolicited.');
    }
}

if ($state) {
    // check that the authentication source is correct
    assert('array_key_exists("saml:sp:AuthId", $state)');
    if ($state['saml:sp:AuthId'] !== $sourceId) {
        throw new SimpleSAML_Error_Exception(
            'The authentication source id in the URL does not match the authentication source which sent the request.'
        );
    }

    // check that the issuer is the one we are expecting
    assert('array_key_exists("ExpectedIssuer", $state)');
    if ($state['ExpectedIssuer'] !== $idp) {
        $idpMetadata = $source->getIdPMetadata($idp);
        $idplist = $idpMetadata->getArrayize('IDPList', array());
        if (!in_array($state['ExpectedIssuer'], $idplist)) {
            throw new SimpleSAML_Error_Exception(
                'The issuer of the response does not match to the identity provider we sent the request to.'
            );
        }
    }
} else {
    // this is an unsolicited response
    $state = array(
        'saml:sp:isUnsolicited' => true,
        'saml:sp:AuthId'        => $sourceId,
        'saml:sp:RelayState'    => \SimpleSAML\Utils\HTTP::checkURLAllowed(
            $spMetadata->getString(
                'RelayState',
                $response->getRelayState()
            )
        ),
    );
}

SimpleSAML_Logger::debug('Received SAML2 Response from '.var_export($idp, true).'.');

if (empty($idpMetadata)) {
    $idpMetadata = $source->getIdPmetadata($idp);
}

try {
    $assertions = sspmod_saml_Message::processResponse($spMetadata, $idpMetadata, $response);
} catch (sspmod_saml_Error $e) {
    // the status of the response wasn't "success"
    $e = $e->toException();
    SimpleSAML_Auth_State::throwException($state, $e);
}


$authenticatingAuthority = null;
$nameId = null;
$sessionIndex = null;
$expire = null;
$attributes = array();
$foundAuthnStatement = false;
foreach ($assertions as $assertion) {

    // check for duplicate assertion (replay attack)
    $store = SimpleSAML_Store::getInstance();
    if ($store !== false) {
        $aID = $assertion->getId();
        if ($store->get('saml.AssertionReceived', $aID) !== null) {
            $e = new SimpleSAML_Error_Exception('Received duplicate assertion.');
            SimpleSAML_Auth_State::throwException($state, $e);
        }

        $notOnOrAfter = $assertion->getNotOnOrAfter();
        if ($notOnOrAfter === null) {
            $notOnOrAfter = time() + 24 * 60 * 60;
        } else {
            $notOnOrAfter += 60; // we allow 60 seconds clock skew, so add it here also
        }

        $store->set('saml.AssertionReceived', $aID, true, $notOnOrAfter);
    }


    if ($authenticatingAuthority === null) {
        $authenticatingAuthority = $assertion->getAuthenticatingAuthority();
    }
    if ($nameId === null) {
        $nameId = $assertion->getNameId();
    }
    if ($sessionIndex === null) {
        $sessionIndex = $assertion->getSessionIndex();
    }
    if ($expire === null) {
        $expire = $assertion->getSessionNotOnOrAfter();
    }

    $attributes = array_merge($attributes, $assertion->getAttributes());

    if ($assertion->getAuthnInstant() !== null) {
        // assertion contains AuthnStatement, since AuthnInstant is a required attribute
        $foundAuthnStatement = true;
    }
}

if (!$foundAuthnStatement) {
    $e = new SimpleSAML_Error_Exception('No AuthnStatement found in assertion(s).');
    SimpleSAML_Auth_State::throwException($state, $e);
}

if ($expire !== null) {
    $logoutExpire = $expire;
} else {
    // just expire the logout association 24 hours into the future
    $logoutExpire = time() + 24 * 60 * 60;
}

// register this session in the logout store
sspmod_saml_SP_LogoutStore::addSession($sourceId, $nameId, $sessionIndex, $logoutExpire);

// we need to save the NameID and SessionIndex for logout
$logoutState = array(
    'saml:logout:Type'         => 'saml2',
    'saml:logout:IdP'          => $idp,
    'saml:logout:NameID'       => $nameId,
    'saml:logout:SessionIndex' => $sessionIndex,
);
$state['LogoutState'] = $logoutState;
$state['saml:AuthenticatingAuthority'] = $authenticatingAuthority;
$state['saml:AuthenticatingAuthority'][] = $idp;
$state['PersistentAuthData'][] = 'saml:AuthenticatingAuthority';

$state['saml:sp:NameID'] = $nameId;
$state['PersistentAuthData'][] = 'saml:sp:NameID';
$state['saml:sp:SessionIndex'] = $sessionIndex;
$state['PersistentAuthData'][] = 'saml:sp:SessionIndex';
$state['saml:sp:AuthnContext'] = $assertion->getAuthnContext();
$state['PersistentAuthData'][] = 'saml:sp:AuthnContext';

if ($expire !== null) {
    $state['Expire'] = $expire;
}

// note some information about the authentication, in case we receive the same response again
$state['saml:sp:prevAuth'] = array(
    'id'     => $response->getId(),
    'issuer' => $idp,
);
if (isset($state['SimpleSAML_Auth_Source.ReturnURL'])) {
    $state['saml:sp:prevAuth']['redirect'] = $state['SimpleSAML_Auth_Source.ReturnURL'];
} elseif (isset($state['saml:sp:RelayState'])) {
    $state['saml:sp:prevAuth']['redirect'] = $state['saml:sp:RelayState'];
}
$state['PersistentAuthData'][] = 'saml:sp:prevAuth';

$source->handleResponse($state, $idp, $attributes);
assert('FALSE');
