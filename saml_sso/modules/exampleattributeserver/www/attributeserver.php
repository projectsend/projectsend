<?php

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();

$binding = SAML2_Binding::getCurrentBinding();
$query = $binding->receive();
if (!($query instanceof SAML2_AttributeQuery)) {
	throw new SimpleSAML_Error_BadRequest('Invalid message received to AttributeQuery endpoint.');
}

$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');


$spEntityId = $query->getIssuer();
if ($spEntityId === NULL) {
	throw new SimpleSAML_Error_BadRequest('Missing <saml:Issuer> in <samlp:AttributeQuery>.');
}

$idpMetadata = $metadata->getMetadataConfig($idpEntityId, 'saml20-idp-hosted');
$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');

// The endpoint we should deliver the message to
$endpoint = $spMetadata->getString('testAttributeEndpoint');

// The attributes we will return
$attributes = array(
	'name' => array('value1', 'value2', 'value3'),
	'test' => array('test'),
);

/* The name format of the attributes. */
$attributeNameFormat = SAML2_Const::NAMEFORMAT_UNSPECIFIED;


/* Determine which attributes we will return. */
$returnAttributes = array_keys($query->getAttributes());
if (count($returnAttributes) === 0) {
	SimpleSAML_Logger::debug('No attributes requested - return all attributes.');
	$returnAttributes = $attributes;

} elseif ($query->getAttributeNameFormat() !== $attributeNameFormat) {
	SimpleSAML_Logger::debug('Requested attributes with wrong NameFormat - no attributes returned.');
	$returnAttributes = array();
} else {
	foreach ($returnAttributes as $name => $values) {
		if (!array_key_exists($name, $attributes)) {
			/* We don't have this attribute. */
			unset($returnAttributes[$name]);
			continue;
		}

		if (count($values) === 0) {
			/* Return all attributes. */
			$returnAttributes[$name] = $attributes[$name];
			continue;
		}

		/* Filter which attribute values we should return. */
		$returnAttributes[$name] = array_intersect($values, $attributes[$name]);
	}
}


/* $returnAttributes contains the attributes we should return. Send them. */
$assertion = new SAML2_Assertion();
$assertion->setIssuer($idpEntityId);
$assertion->setNameId($query->getNameId());
$assertion->setNotBefore(time());
$assertion->setNotOnOrAfter(time() + 5*60);
$assertion->setValidAudiences(array($spEntityId));
$assertion->setAttributes($returnAttributes);
$assertion->setAttributeNameFormat($attributeNameFormat);

$sc = new SAML2_XML_saml_SubjectConfirmation();
$sc->Method = SAML2_Const::CM_BEARER;
$sc->SubjectConfirmationData = new SAML2_XML_saml_SubjectConfirmationData();
$sc->SubjectConfirmationData->NotOnOrAfter = time() + 5*60;
$sc->SubjectConfirmationData->Recipient = $endpoint;
$sc->SubjectConfirmationData->InResponseTo = $query->getId();
$assertion->setSubjectConfirmation(array($sc));

sspmod_saml_Message::addSign($idpMetadata, $spMetadata, $assertion);

$response = new SAML2_Response();
$response->setRelayState($query->getRelayState());
$response->setDestination($endpoint);
$response->setIssuer($idpEntityId);
$response->setInResponseTo($query->getId());
$response->setAssertions(array($assertion));
sspmod_saml_Message::addSign($idpMetadata, $spMetadata, $response);

$binding = new SAML2_HTTPPost();
$binding->send($response);
