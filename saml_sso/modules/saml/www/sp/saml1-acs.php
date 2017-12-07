<?php

if (!array_key_exists('SAMLResponse', $_REQUEST) && !array_key_exists('SAMLart', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing SAMLResponse or SAMLart parameter.');
}

if (!array_key_exists('TARGET', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing TARGET parameter.');
}

if (!array_key_exists('PATH_INFO', $_SERVER)) {
    throw new SimpleSAML_Error_BadRequest('Missing authentication source ID in assertion consumer service URL');
}

$sourceId = $_SERVER['PATH_INFO'];
$end = strpos($sourceId, '/', 1);
if ($end === FALSE) {
	$end = strlen($sourceId);
}
$sourceId = substr($sourceId, 1, $end - 1);

$source = SimpleSAML_Auth_Source::getById($sourceId, 'sspmod_saml_Auth_Source_SP');

SimpleSAML_Logger::debug('Received SAML1 response');

$target = (string)$_REQUEST['TARGET'];

if (preg_match('@^https?://@i', $target)) {
	// Unsolicited response
	$state = array(
		'saml:sp:isUnsolicited' => TRUE,
		'saml:sp:AuthId' => $sourceId,
		'saml:sp:RelayState' => \SimpleSAML\Utils\HTTP::checkURLAllowed($target),
	);
} else {
	$state = SimpleSAML_Auth_State::loadState($_REQUEST['TARGET'], 'saml:sp:sso');

	// Check that the authentication source is correct.
	assert('array_key_exists("saml:sp:AuthId", $state)');
	if ($state['saml:sp:AuthId'] !== $sourceId) {
		throw new SimpleSAML_Error_Exception('The authentication source id in the URL does not match the authentication source which sent the request.');
	}

	assert('isset($state["saml:idp"])');
}

$spMetadata = $source->getMetadata();

if (array_key_exists('SAMLart', $_REQUEST)) {
	if (!isset($state['saml:idp'])) {
		/* Unsolicited response. */
		throw new SimpleSAML_Error_Exception('IdP initiated authentication not supported with the SAML 1.1 SAMLart protocol.');
	}
	$idpMetadata = $source->getIdPMetadata($state['saml:idp']);

	$responseXML = SimpleSAML_Bindings_Shib13_Artifact::receive($spMetadata, $idpMetadata);
	$isValidated = TRUE; /* Artifact binding validated with ssl certificate. */
} elseif (array_key_exists('SAMLResponse', $_REQUEST)) {
	$responseXML = $_REQUEST['SAMLResponse'];
	$responseXML = base64_decode($responseXML);
	$isValidated = FALSE; /* Must check signature on response. */
} else {
	assert('FALSE');
}

$response = new SimpleSAML_XML_Shib13_AuthnResponse();
$response->setXML($responseXML);

$response->setMessageValidated($isValidated);
$response->validate();

$responseIssuer = $response->getIssuer();
$attributes = $response->getAttributes();

if (isset($state['saml:idp']) && $responseIssuer !== $state['saml:idp']) {
	throw new SimpleSAML_Error_Exception('The issuer of the response wasn\'t the destination of the request.');
}

$logoutState = array(
	'saml:logout:Type' => 'saml1'
	);
$state['LogoutState'] = $logoutState;

$state['saml:sp:NameID'] = $response->getNameID();

$source->handleResponse($state, $responseIssuer, $attributes);
assert('FALSE');
