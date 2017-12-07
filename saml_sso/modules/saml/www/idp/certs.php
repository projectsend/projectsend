<?php

// Load SimpleSAMLphp, configuration and metadata
$config = SimpleSAML_Configuration::getInstance();
$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();

if (!$config->getBoolean('enable.saml20-idp', false))
	throw new SimpleSAML_Error_Error('NOACCESS');

// Check if valid local session exists..
if ($config->getBoolean('admin.protectmetadata', false)) {
    SimpleSAML\Utils\Auth::requireAdmin();
}

$idpentityid = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
$idpmeta = $metadata->getMetaDataConfig($idpentityid, 'saml20-idp-hosted');

switch($_SERVER['PATH_INFO']) {
	case '/new_idp.crt':
		$certInfo = SimpleSAML\Utils\Crypto::loadPublicKey($idpmeta, FALSE, 'new_');
		break;
	case '/idp.crt':
		$certInfo = SimpleSAML\Utils\Crypto::loadPublicKey($idpmeta, TRUE);
		break;
	case '/https.crt':
		$certInfo = SimpleSAML\Utils\Crypto::loadPublicKey($idpmeta, TRUE, 'https.');
		break;
	default:
		throw new SimpleSAML_Error_NotFound('Unknown certificate.');
}

header('Content-Disposition: attachment; filename='.substr($_SERVER['PATH_INFO'], 1));
header('Content-Type: application/x-x509-ca-cert');
echo $certInfo['PEM'];
exit(0);
