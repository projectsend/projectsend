<?php
/**
 * The SSOService is part of the Shibboleth 1.3 IdP code, and it receives incoming Authentication Requests
 * from a Shibboleth 1.3 SP, parses, and process it, and then authenticates the user and sends the user back
 * to the SP with an Authentication Response.
 *
 * @author Andreas Åkre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */

require_once('../../_include.php');

SimpleSAML_Logger::info('Shib1.3 - IdP.SSOService: Accessing Shibboleth 1.3 IdP endpoint SSOService');

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$idpEntityId = $metadata->getMetaDataCurrentEntityID('shib13-idp-hosted');
$idp = SimpleSAML_IdP::getById('saml1:' . $idpEntityId);
sspmod_saml_IdP_SAML1::receiveAuthnRequest($idp);
assert('FALSE');
