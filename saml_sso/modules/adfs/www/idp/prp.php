<?php
/**
 * ADFS PRP IDP protocol support for SimpleSAMLphp.
 *
 * @author Hans Zandbelt, SURFnet bv, <hans.zandbelt@surfnet.nl>
 * @package SimpleSAMLphp
 */

SimpleSAML_Logger::info('ADFS - IdP.prp: Accessing ADFS IdP endpoint prp');

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$idpEntityId = $metadata->getMetaDataCurrentEntityID('adfs-idp-hosted');
$idp = SimpleSAML_IdP::getById('adfs:' . $idpEntityId);

if (isset($_GET['wa'])) {
	if ($_GET['wa'] === 'wsignout1.0') {
		sspmod_adfs_IdP_ADFS::receiveLogoutMessage($idp);
	} else if ($_GET['wa'] === 'wsignin1.0') {
		sspmod_adfs_IdP_ADFS::receiveAuthnRequest($idp);
	}
	assert('FALSE');		
} elseif(isset($_GET['assocId'])) {
	// logout response from ADFS SP
	$assocId = $_GET['assocId']; // Association ID of the SP that sent the logout response
	$relayState = $_GET['relayState']; // Data that was sent in the logout request to the SP. Can be null
	$logoutError = NULL; /* NULL on success, or an instance of a SimpleSAML_Error_Exception on failure. */
	$idp->handleLogoutResponse($assocId, $relayState, $logoutError);
}
