<?php


/**
 * This class implements a helper function for signing of metadata.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_Metadata_Signer
{

    /**
     * This functions finds what key & certificate files should be used to sign the metadata
     * for the given entity.
     *
     * @param SimpleSAML_Configuration $config Our SimpleSAML_Configuration instance.
     * @param array                    $entityMetadata The metadata of the entity.
     * @param string                   $type A string which describes the type entity this is, e.g. 'SAML 2 IdP' or
     *     'Shib 1.3 SP'.
     *
     * @return array An associative array with the keys 'privatekey', 'certificate', and optionally 'privatekey_pass'.
     * @throws Exception If the key and certificate used to sign is unknown.
     */
    private static function findKeyCert($config, $entityMetadata, $type)
    {
        // first we look for metadata.privatekey and metadata.certificate in the metadata
        if (array_key_exists('metadata.sign.privatekey', $entityMetadata)
            || array_key_exists('metadata.sign.certificate', $entityMetadata)
        ) {

            if (!array_key_exists('metadata.sign.privatekey', $entityMetadata)
                || !array_key_exists('metadata.sign.certificate', $entityMetadata)
            ) {

                throw new Exception(
                    'Missing either the "metadata.sign.privatekey" or the'.
                    ' "metadata.sign.certificate" configuration option in the metadata for'.
                    ' the '.$type.' "'.$entityMetadata['entityid'].'". If one of'.
                    ' these options is specified, then the other must also be specified.'
                );
            }

            $ret = array(
                'privatekey'  => $entityMetadata['metadata.sign.privatekey'],
                'certificate' => $entityMetadata['metadata.sign.certificate']
            );

            if (array_key_exists('metadata.sign.privatekey_pass', $entityMetadata)) {
                $ret['privatekey_pass'] = $entityMetadata['metadata.sign.privatekey_pass'];
            }

            return $ret;
        }

        // then we look for default values in the global configuration
        $privatekey = $config->getString('metadata.sign.privatekey', null);
        $certificate = $config->getString('metadata.sign.certificate', null);
        if ($privatekey !== null || $certificate !== null) {
            if ($privatekey === null || $certificate === null) {
                throw new Exception(
                    'Missing either the "metadata.sign.privatekey" or the'.
                    ' "metadata.sign.certificate" configuration option in the global'.
                    ' configuration. If one of these options is specified, then the other'.
                    ' must also be specified.'
                );
            }
            $ret = array('privatekey' => $privatekey, 'certificate' => $certificate);

            $privatekey_pass = $config->getString('metadata.sign.privatekey_pass', null);
            if ($privatekey_pass !== null) {
                $ret['privatekey_pass'] = $privatekey_pass;
            }

            return $ret;
        }

        // as a last resort we attempt to use the privatekey and certificate option from the metadata
        if (array_key_exists('privatekey', $entityMetadata)
            || array_key_exists('certificate', $entityMetadata)
        ) {

            if (!array_key_exists('privatekey', $entityMetadata)
                || !array_key_exists('certificate', $entityMetadata)
            ) {
                throw new Exception(
                    'Both the "privatekey" and the "certificate" option must'.
                    ' be set in the metadata for the '.$type.' "'.
                    $entityMetadata['entityid'].'" before it is possible to sign metadata'.
                    ' from this entity.'
                );
            }

            $ret = array(
                'privatekey'  => $entityMetadata['privatekey'],
                'certificate' => $entityMetadata['certificate']
            );

            if (array_key_exists('privatekey_pass', $entityMetadata)) {
                $ret['privatekey_pass'] = $entityMetadata['privatekey_pass'];
            }

            return $ret;
        }

        throw new Exception(
            'Could not find what key & certificate should be used to sign the metadata'.
            ' for the '.$type.' "'.$entityMetadata['entityid'].'".'
        );
    }


    /**
     * Determine whether metadata signing is enabled for the given metadata.
     *
     * @param SimpleSAML_Configuration $config Our SimpleSAML_Configuration instance.
     * @param array                    $entityMetadata The metadata of the entity.
     * @param string                   $type A string which describes the type entity this is, e.g. 'SAML 2 IdP' or
     *     'Shib 1.3 SP'.
     *
     * @return boolean True if metadata signing is enabled, false otherwise.
     * @throws Exception If the value of the 'metadata.sign.enable' option is not a boolean.
     */
    private static function isMetadataSigningEnabled($config, $entityMetadata, $type)
    {
        // first check the metadata for the entity
        if (array_key_exists('metadata.sign.enable', $entityMetadata)) {
            if (!is_bool($entityMetadata['metadata.sign.enable'])) {
                throw new Exception(
                    'Invalid value for the "metadata.sign.enable" configuration option for'.
                    ' the '.$type.' "'.$entityMetadata['entityid'].'". This option'.
                    ' should be a boolean.'
                );
            }

            return $entityMetadata['metadata.sign.enable'];
        }

        $enabled = $config->getBoolean('metadata.sign.enable', false);

        return $enabled;
    }


    /**
     * Signs the given metadata if metadata signing is enabled.
     *
     * @param string $metadataString A string with the metadata.
     * @param array  $entityMetadata The metadata of the entity.
     * @param string $type A string which describes the type entity this is, e.g. 'SAML 2 IdP' or 'Shib 1.3 SP'.
     *
     * @return string The $metadataString with the signature embedded.
     * @throws Exception If the certificate or private key cannot be loaded, or the metadata doesn't parse properly.
     */
    public static function sign($metadataString, $entityMetadata, $type)
    {
        $config = SimpleSAML_Configuration::getInstance();

        // check if metadata signing is enabled
        if (!self::isMetadataSigningEnabled($config, $entityMetadata, $type)) {
            return $metadataString;
        }

        // find the key & certificate which should be used to sign the metadata
        $keyCertFiles = self::findKeyCert($config, $entityMetadata, $type);

        $keyFile = \SimpleSAML\Utils\Config::getCertPath($keyCertFiles['privatekey']);
        if (!file_exists($keyFile)) {
            throw new Exception('Could not find private key file ['.$keyFile.'], which is needed to sign the metadata');
        }
        $keyData = file_get_contents($keyFile);

        $certFile = \SimpleSAML\Utils\Config::getCertPath($keyCertFiles['certificate']);
        if (!file_exists($certFile)) {
            throw new Exception(
                'Could not find certificate file ['.$certFile.'], which is needed to sign the metadata'
            );
        }
        $certData = file_get_contents($certFile);


        // convert the metadata to a DOM tree
        try {
            $xml = SAML2_DOMDocumentFactory::fromString($metadataString);
        } catch(Exception $e) {
            throw new Exception('Error parsing self-generated metadata.');
        }

        // load the private key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));
        if (array_key_exists('privatekey_pass', $keyCertFiles)) {
            $objKey->passphrase = $keyCertFiles['privatekey_pass'];
        }
        $objKey->loadKey($keyData, false);

        // get the EntityDescriptor node we should sign
        $rootNode = $xml->firstChild;

        // sign the metadata with our private key
        if ($type == 'ADFS IdP') {
            $objXMLSecDSig = new sspmod_adfs_XMLSecurityDSig($metadataString);
        } else {
            $objXMLSecDSig = new XMLSecurityDSig();
        }

        $objXMLSecDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

        $objXMLSecDSig->addReferenceList(
            array($rootNode),
            XMLSecurityDSig::SHA1,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
            array('id_name' => 'ID')
        );

        $objXMLSecDSig->sign($objKey);

        // add the certificate to the signature
        $objXMLSecDSig->add509Cert($certData, true);

        // add the signature to the metadata
        $objXMLSecDSig->insertSignature($rootNode, $rootNode->firstChild);

        // return the DOM tree as a string
        return $xml->saveXML();
    }
}
