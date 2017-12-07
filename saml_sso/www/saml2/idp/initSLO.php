<?php
require_once('../../_include.php');

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
$idp = SimpleSAML_IdP::getById('saml2:' . $idpEntityId);

SimpleSAML_Logger::info('SAML2.0 - IdP.initSLO: Accessing SAML 2.0 IdP endpoint init Single Logout');

if (!isset($_GET['RelayState'])) {
	throw new SimpleSAML_Error_Error('NORELAYSTATE');
}

$idp->doLogoutRedirect(\SimpleSAML\Utils\HTTP::checkURLAllowed((string)$_GET['RelayState']));
assert('FALSE');