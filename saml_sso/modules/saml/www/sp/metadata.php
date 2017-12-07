<?php

if (!array_key_exists('PATH_INFO', $_SERVER)) {
    throw new SimpleSAML_Error_BadRequest('Missing authentication source id in metadata URL');
}

$config = SimpleSAML_Configuration::getInstance();
if ($config->getBoolean('admin.protectmetadata', false)) {
    SimpleSAML\Utils\Auth::requireAdmin();
}
$sourceId = substr($_SERVER['PATH_INFO'], 1);
$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === null) {
    throw new SimpleSAML_Error_NotFound('Could not find authentication source with id '.$sourceId);
}

if (!($source instanceof sspmod_saml_Auth_Source_SP)) {
    throw new SimpleSAML_Error_NotFound('Source isn\'t a SAML SP: '.var_export($sourceId, true));
}

$entityId = $source->getEntityId();
$spconfig = $source->getMetadata();
$store = SimpleSAML_Store::getInstance();

$metaArray20 = array();

$slosvcdefault = array(
    SAML2_Const::BINDING_HTTP_REDIRECT,
    SAML2_Const::BINDING_SOAP,
);

$slob = $spconfig->getArray('SingleLogoutServiceBinding', $slosvcdefault);
$slol = SimpleSAML_Module::getModuleURL('saml/sp/saml2-logout.php/'.$sourceId);

foreach ($slob as $binding) {
    if ($binding == SAML2_Const::BINDING_SOAP && !($store instanceof SimpleSAML_Store_SQL)) {
        // we cannot properly support SOAP logout
        continue;
    }
    $metaArray20['SingleLogoutService'][] = array(
        'Binding'  => $binding,
        'Location' => $slol,
    );
}

$assertionsconsumerservicesdefault = array(
    'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
    'urn:oasis:names:tc:SAML:1.0:profiles:browser-post',
    'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
    'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01',
);

if ($spconfig->getString('ProtocolBinding', '') == 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser') {
    $assertionsconsumerservicesdefault[] = 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser';
}

$assertionsconsumerservices = $spconfig->getArray('acs.Bindings', $assertionsconsumerservicesdefault);

$index = 0;
$eps = array();
foreach ($assertionsconsumerservices as $services) {

    $acsArray = array('index' => $index);
    switch ($services) {
        case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST':
            $acsArray['Binding'] = SAML2_Const::BINDING_HTTP_POST;
            $acsArray['Location'] = SimpleSAML_Module::getModuleURL('saml/sp/saml2-acs.php/'.$sourceId);
            break;
        case 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post';
            $acsArray['Location'] = SimpleSAML_Module::getModuleURL('saml/sp/saml1-acs.php/'.$sourceId);
            break;
        case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact';
            $acsArray['Location'] = SimpleSAML_Module::getModuleURL('saml/sp/saml2-acs.php/'.$sourceId);
            break;
        case 'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01';
            $acsArray['Location'] = SimpleSAML_Module::getModuleURL('saml/sp/saml1-acs.php/'.$sourceId.'/artifact');
            break;
        case 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser';
            $acsArray['Location'] = SimpleSAML_Module::getModuleURL('saml/sp/saml2-acs.php/'.$sourceId);
            $acsArray['hoksso:ProtocolBinding'] = SAML2_Const::BINDING_HTTP_REDIRECT;
            break;
    }
    $eps[] = $acsArray;
    $index++;
}

$metaArray20['AssertionConsumerService'] = $eps;

$keys = array();
$certInfo = SimpleSAML\Utils\Crypto::loadPublicKey($spconfig, false, 'new_');
if ($certInfo !== null && array_key_exists('certData', $certInfo)) {
    $hasNewCert = true;

    $certData = $certInfo['certData'];

    $keys[] = array(
        'type'            => 'X509Certificate',
        'signing'         => true,
        'encryption'      => true,
        'X509Certificate' => $certInfo['certData'],
    );
} else {
    $hasNewCert = false;
}

$certInfo = SimpleSAML\Utils\Crypto::loadPublicKey($spconfig);
if ($certInfo !== null && array_key_exists('certData', $certInfo)) {
    $certData = $certInfo['certData'];

    $keys[] = array(
        'type'            => 'X509Certificate',
        'signing'         => true,
        'encryption'      => ($hasNewCert ? false : true),
        'X509Certificate' => $certInfo['certData'],
    );
} else {
    $certData = null;
}

$format = $spconfig->getString('NameIDPolicy', null);
if ($format !== null) {
    $metaArray20['NameIDFormat'] = $format;
}

$name = $spconfig->getLocalizedString('name', null);
$attributes = $spconfig->getArray('attributes', array());

if ($name !== null && !empty($attributes)) {
    $metaArray20['name'] = $name;
    $metaArray20['attributes'] = $attributes;
    $metaArray20['attributes.required'] = $spconfig->getArray('attributes.required', array());

    if (empty($metaArray20['attributes.required'])) {
        unset($metaArray20['attributes.required']);
    }

    $description = $spconfig->getArray('description', null);
    if ($description !== null) {
        $metaArray20['description'] = $description;
    }

    $nameFormat = $spconfig->getString('attributes.NameFormat', null);
    if ($nameFormat !== null) {
        $metaArray20['attributes.NameFormat'] = $nameFormat;
    }
}

// add organization info
$orgName = $spconfig->getLocalizedString('OrganizationName', null);
if ($orgName !== null) {
    $metaArray20['OrganizationName'] = $orgName;

    $metaArray20['OrganizationDisplayName'] = $spconfig->getLocalizedString('OrganizationDisplayName', null);
    if ($metaArray20['OrganizationDisplayName'] === null) {
        $metaArray20['OrganizationDisplayName'] = $orgName;
    }

    $metaArray20['OrganizationURL'] = $spconfig->getLocalizedString('OrganizationURL', null);
    if ($metaArray20['OrganizationURL'] === null) {
        throw new SimpleSAML_Error_Exception('If OrganizationName is set, OrganizationURL must also be set.');
    }
}

if ($spconfig->hasValue('contacts')) {
    $contacts = $spconfig->getArray('contacts');
    foreach ($contacts as $contact) {
        $metaArray20['contacts'][] = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
    }
}

// add technical contact
$email = $config->getString('technicalcontact_email', 'na@example.org', false);
if ($email && $email !== 'na@example.org') {
    $techcontact['emailAddress'] = $email;
    $techcontact['name'] = $config->getString('technicalcontact_name', null);
    $techcontact['contactType'] = 'technical';
    $metaArray20['contacts'][] = \SimpleSAML\Utils\Config\Metadata::getContact($techcontact);
}

// add certificate
if (count($keys) === 1) {
    $metaArray20['certData'] = $keys[0]['X509Certificate'];
} elseif (count($keys) > 1) {
    $metaArray20['keys'] = $keys;
}

// add EntityAttributes extension
if ($spconfig->hasValue('EntityAttributes')) {
    $metaArray20['EntityAttributes'] = $spconfig->getArray('EntityAttributes');
}

// add UIInfo extension
if ($spconfig->hasValue('UIInfo')) {
    $metaArray20['UIInfo'] = $spconfig->getArray('UIInfo');
}

// add RegistrationInfo extension
if ($spconfig->hasValue('RegistrationInfo')) {
    $metaArray20['RegistrationInfo'] = $spconfig->getArray('RegistrationInfo');
}

// add signature options
if ($spconfig->hasValue('WantAssertionsSigned')) {
    $metaArray20['saml20.sign.assertion'] = $spconfig->getBoolean('WantAssertionsSigned');
}
if ($spconfig->hasValue('redirect.sign')) {
    $metaArray20['redirect.validate'] = $spconfig->getBoolean('redirect.sign');
} elseif ($spconfig->hasValue('sign.authnrequest')) {
    $metaArray20['validate.authnrequest'] = $spconfig->getBoolean('sign.authnrequest');
}

$supported_protocols = array('urn:oasis:names:tc:SAML:1.1:protocol', SAML2_Const::NS_SAMLP);

$metaArray20['metadata-set'] = 'saml20-sp-remote';
$metaArray20['entityid'] = $entityId;

$metaBuilder = new SimpleSAML_Metadata_SAMLBuilder($entityId);
$metaBuilder->addMetadataSP20($metaArray20, $supported_protocols);
$metaBuilder->addOrganizationInfo($metaArray20);

$xml = $metaBuilder->getEntityDescriptorText();

unset($metaArray20['UIInfo']);
unset($metaArray20['metadata-set']);
unset($metaArray20['entityid']);

// sanitize the attributes array to remove friendly names
if (isset($metaArray20['attributes']) && is_array($metaArray20['attributes'])) {
    $metaArray20['attributes'] = array_values($metaArray20['attributes']);
}

// sign the metadata if enabled
$xml = SimpleSAML_Metadata_Signer::sign($xml, $spconfig->toArray(), 'SAML 2 SP');

if (array_key_exists('output', $_REQUEST) && $_REQUEST['output'] == 'xhtml') {

    $t = new SimpleSAML_XHTML_Template($config, 'metadata.php', 'admin');

    $t->data['clipboard.js'] = true;
    $t->data['header'] = 'saml20-sp';
    $t->data['metadata'] = htmlspecialchars($xml);
    $t->data['metadataflat'] = '$metadata['.var_export($entityId, true).'] = '.var_export($metaArray20, true).';';
    $t->data['metaurl'] = $source->getMetadataURL();
    $t->show();
} else {
    header('Content-Type: application/samlmetadata+xml');
    echo($xml);
}
