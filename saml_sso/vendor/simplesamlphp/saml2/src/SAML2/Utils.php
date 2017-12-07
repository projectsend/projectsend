<?php

/**
 * Helper functions for the SAML2 library.
 *
 * @package SimpleSAMLphp
 */
class SAML2_Utils
{
    /**
     * Check the Signature in a XML element.
     *
     * This function expects the XML element to contain a Signature-element
     * which contains a reference to the XML-element. This is common for both
     * messages and assertions.
     *
     * Note that this function only validates the element itself. It does not
     * check this against any local keys.
     *
     * If no Signature-element is located, this function will return FALSE. All
     * other validation errors result in an exception. On successful validation
     * an array will be returned. This array contains the information required to
     * check the signature against a public key.
     *
     * @param  DOMElement  $root The element which should be validated.
     * @return array|bool An array with information about the Signature-element.
     * @throws Exception
     */
    public static function validateElement(DOMElement $root)
    {
        /* Create an XML security object. */
        $objXMLSecDSig = new XMLSecurityDSig();

        /* Both SAML messages and SAML assertions use the 'ID' attribute. */
        $objXMLSecDSig->idKeys[] = 'ID';

        /* Locate the XMLDSig Signature element to be used. */
        $signatureElement = self::xpQuery($root, './ds:Signature');
        if (count($signatureElement) === 0) {
            /* We don't have a signature element ot validate. */

            return FALSE;
        } elseif (count($signatureElement) > 1) {
            throw new Exception('XMLSec: more than one signature element in root.');
        }
        $signatureElement = $signatureElement[0];
        $objXMLSecDSig->sigNode = $signatureElement;

        /* Canonicalize the XMLDSig SignedInfo element in the message. */
        $objXMLSecDSig->canonicalizeSignedInfo();

        /* Validate referenced xml nodes. */
        if (!$objXMLSecDSig->validateReference()) {
            throw new Exception('XMLsec: digest validation failed');
        }

        /* Check that $root is one of the signed nodes. */
        $rootSigned = FALSE;
        /** @var DOMNode $signedNode */
        foreach ($objXMLSecDSig->getValidatedNodes() as $signedNode) {
            if ($signedNode->isSameNode($root)) {
                $rootSigned = TRUE;
                break;
            } elseif ($root->parentNode instanceof DOMDocument && $signedNode->isSameNode($root->ownerDocument)) {
                /* $root is the root element of a signed document. */
                $rootSigned = TRUE;
                break;
            }
        }
        if (!$rootSigned) {
            throw new Exception('XMLSec: The root element is not signed.');
        }

        /* Now we extract all available X509 certificates in the signature element. */
        $certificates = array();
        foreach (self::xpQuery($signatureElement, './ds:KeyInfo/ds:X509Data/ds:X509Certificate') as $certNode) {
            $certData = trim($certNode->textContent);
            $certData = str_replace(array("\r", "\n", "\t", ' '), '', $certData);
            $certificates[] = $certData;
        }

        $ret = array(
            'Signature' => $objXMLSecDSig,
            'Certificates' => $certificates,
            );

        return $ret;
    }


    /**
     * Helper function to convert a XMLSecurityKey to the correct algorithm.
     *
     * @param  XMLSecurityKey $key       The key.
     * @param  string         $algorithm The desired algorithm.
     * @param  string         $type      Public or private key, defaults to public.
     * @return XMLSecurityKey The new key.
     * @throws Exception
     */
    public static function castKey(XMLSecurityKey $key, $algorithm, $type = 'public')
    {
        assert('is_string($algorithm)');
        assert('$type === "public" || $type === "private"');

        // do nothing if algorithm is already the type of the key
        if ($key->type === $algorithm) {
            return $key;
        }

        $keyInfo = openssl_pkey_get_details($key->key);
        if ($keyInfo === FALSE) {
            throw new Exception('Unable to get key details from XMLSecurityKey.');
        }
        if (!isset($keyInfo['key'])) {
            throw new Exception('Missing key in public key details.');
        }

        $newKey = new XMLSecurityKey($algorithm, array('type'=>$type));
        $newKey->loadKey($keyInfo['key']);

        return $newKey;
    }


    /**
     * Check a signature against a key.
     *
     * An exception is thrown if we are unable to validate the signature.
     *
     * @param array          $info The information returned by the validateElement()-function.
     * @param XMLSecurityKey $key  The publickey that should validate the Signature object.
     * @throws Exception
     */
    public static function validateSignature(array $info, XMLSecurityKey $key)
    {
        assert('array_key_exists("Signature", $info)');

        /** @var XMLSecurityDSig $objXMLSecDSig */
        $objXMLSecDSig = $info['Signature'];

        $sigMethod = self::xpQuery($objXMLSecDSig->sigNode, './ds:SignedInfo/ds:SignatureMethod');
        if (empty($sigMethod)) {
            throw new Exception('Missing SignatureMethod element.');
        }
        $sigMethod = $sigMethod[0];
        if (!$sigMethod->hasAttribute('Algorithm')) {
            throw new Exception('Missing Algorithm-attribute on SignatureMethod element.');
        }
        $algo = $sigMethod->getAttribute('Algorithm');

        if ($key->type === XMLSecurityKey::RSA_SHA1 && $algo !== $key->type) {
            $key = self::castKey($key, $algo);
        }

        /* Check the signature. */
        if ($objXMLSecDSig->verify($key) !== 1) {
            throw new Exception("Unable to validate Signature");
        }
    }


    /**
     * Do an XPath query on an XML node.
     *
     * @param  DOMNode $node  The XML node.
     * @param  string  $query The query.
     * @return DOMElement[]    Array with matching DOM nodes.
     */
    public static function xpQuery(DOMNode $node, $query)
    {
        assert('is_string($query)');
        static $xpCache = NULL;

        if ($node instanceof DOMDocument) {
            $doc = $node;
        } else {
            $doc = $node->ownerDocument;
        }

        if ($xpCache === NULL || !$xpCache->document->isSameNode($doc)) {
            $xpCache = new DOMXPath($doc);
            $xpCache->registerNamespace('soap-env', SAML2_Const::NS_SOAP);
            $xpCache->registerNamespace('saml_protocol', SAML2_Const::NS_SAMLP);
            $xpCache->registerNamespace('saml_assertion', SAML2_Const::NS_SAML);
            $xpCache->registerNamespace('saml_metadata', SAML2_Const::NS_MD);
            $xpCache->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
            $xpCache->registerNamespace('xenc', XMLSecEnc::XMLENCNS);
        }

        $results = $xpCache->query($query, $node);
        $ret = array();
        for ($i = 0; $i < $results->length; $i++) {
            $ret[$i] = $results->item($i);
        }

        return $ret;
    }


    /**
     * Make an exact copy the specific DOMElement.
     *
     * @param  DOMElement      $element The element we should copy.
     * @param  DOMElement|NULL $parent  The target parent element.
     * @return DOMElement      The copied element.
     */
    public static function copyElement(DOMElement $element, DOMElement $parent = NULL)
    {
        if ($parent === NULL) {
            $document = SAML2_DOMDocumentFactory::create();
        } else {
            $document = $parent->ownerDocument;
        }

        $namespaces = array();
        for ($e = $element; $e !== NULL; $e = $e->parentNode) {
            foreach (SAML2_Utils::xpQuery($e, './namespace::*') as $ns) {
                $prefix = $ns->localName;
                if ($prefix === 'xml' || $prefix === 'xmlns') {
                    continue;
                }
                $uri = $ns->nodeValue;
                if (!isset($namespaces[$prefix])) {
                    $namespaces[$prefix] = $uri;
                }
            }
        }

        /** @var DOMElement $newElement */
        $newElement = $document->importNode($element, TRUE);
        if ($parent !== NULL) {
            /* We need to append the child to the parent before we add the namespaces. */
            $parent->appendChild($newElement);
        }

        foreach ($namespaces as $prefix => $uri) {
            $newElement->setAttributeNS($uri, $prefix . ':__ns_workaround__', 'tmp');
            $newElement->removeAttributeNS($uri, '__ns_workaround__');
        }

        return $newElement;
    }


    /**
     * Parse a boolean attribute.
     *
     * @param  DOMElement $node          The element we should fetch the attribute from.
     * @param  string     $attributeName The name of the attribute.
     * @param  mixed      $default       The value that should be returned if the attribute doesn't exist.
     * @return bool|mixed The value of the attribute, or $default if the attribute doesn't exist.
     * @throws Exception
     */
    public static function parseBoolean(DOMElement $node, $attributeName, $default = NULL)
    {
        assert('is_string($attributeName)');

        if (!$node->hasAttribute($attributeName)) {
            return $default;
        }
        $value = $node->getAttribute($attributeName);
        switch (strtolower($value)) {
            case '0':
            case 'false':
                return FALSE;
            case '1':
            case 'true':
                return TRUE;
            default:
                throw new Exception('Invalid value of boolean attribute ' . var_export($attributeName, TRUE) . ': ' . var_export($value, TRUE));
        }
    }


    /**
     * Create a NameID element.
     *
     * The NameId array can have the following elements: 'Value', 'Format',
     *   'NameQualifier, 'SPNameQualifier'
     *
     * Only the 'Value'-element is required.
     *
     * @param DOMElement $node   The DOM node we should append the NameId to.
     * @param array      $nameId The name identifier.
     */
    public static function addNameId(DOMElement $node, array $nameId)
    {
        assert('array_key_exists("Value", $nameId)');

        $xml = SAML2_Utils::addString($node, SAML2_Const::NS_SAML, 'saml:NameID', $nameId['Value']);

        if (array_key_exists('NameQualifier', $nameId) && $nameId['NameQualifier'] !== NULL) {
            $xml->setAttribute('NameQualifier', $nameId['NameQualifier']);
        }
        if (array_key_exists('SPNameQualifier', $nameId) && $nameId['SPNameQualifier'] !== NULL) {
            $xml->setAttribute('SPNameQualifier', $nameId['SPNameQualifier']);
        }
        if (array_key_exists('Format', $nameId) && $nameId['Format'] !== NULL) {
            $xml->setAttribute('Format', $nameId['Format']);
        }
    }

    /**
     * Parse a NameID element.
     *
     * @param  DOMElement $xml The DOM element we should parse.
     * @return array      The parsed name identifier.
     */
    public static function parseNameId(DOMElement $xml)
    {
        $ret = array('Value' => trim($xml->textContent));

        foreach (array('NameQualifier', 'SPNameQualifier', 'Format') as $attr) {
            if ($xml->hasAttribute($attr)) {
                $ret[$attr] = $xml->getAttribute($attr);
            }
        }

        return $ret;
    }

    /**
     * Insert a Signature-node.
     *
     * @param XMLSecurityKey $key           The key we should use to sign the message.
     * @param array          $certificates  The certificates we should add to the signature node.
     * @param DOMElement     $root          The XML node we should sign.
     * @param DOMNode        $insertBefore  The XML element we should insert the signature element before.
     */
    public static function insertSignature(
        XMLSecurityKey $key,
        array $certificates,
        DOMElement $root,
        DOMNode $insertBefore = NULL
    ) {
        $objXMLSecDSig = new XMLSecurityDSig();
        $objXMLSecDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

        switch ($key->type) {
            case XMLSecurityKey::RSA_SHA256:
                $type = XMLSecurityDSig::SHA256;
                break;
            case XMLSecurityKey::RSA_SHA384:
                $type = XMLSecurityDSig::SHA384;
                break;
            case XMLSecurityKey::RSA_SHA512:
                $type = XMLSecurityDSig::SHA512;
                break;
            default:
                $type = XMLSecurityDSig::SHA1;
        }

        $objXMLSecDSig->addReferenceList(
            array($root),
            $type,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
            array('id_name' => 'ID', 'overwrite' => FALSE)
        );

        $objXMLSecDSig->sign($key);

        foreach ($certificates as $certificate) {
            $objXMLSecDSig->add509Cert($certificate, TRUE);
        }

        $objXMLSecDSig->insertSignature($root, $insertBefore);

    }

    /**
     * Decrypt an encrypted element.
     *
     * This is an internal helper function.
     *
     * @param  DOMElement     $encryptedData The encrypted data.
     * @param  XMLSecurityKey $inputKey      The decryption key.
     * @param  array          &$blacklist    Blacklisted decryption algorithms.
     * @return DOMElement     The decrypted element.
     * @throws Exception
     */
    private static function doDecryptElement(DOMElement $encryptedData, XMLSecurityKey $inputKey, array &$blacklist)
    {
        $enc = new XMLSecEnc();

        $enc->setNode($encryptedData);
        $enc->type = $encryptedData->getAttribute("Type");

        $symmetricKey = $enc->locateKey($encryptedData);
        if (!$symmetricKey) {
            throw new Exception('Could not locate key algorithm in encrypted data.');
        }

        $symmetricKeyInfo = $enc->locateKeyInfo($symmetricKey);
        if (!$symmetricKeyInfo) {
            throw new Exception('Could not locate <dsig:KeyInfo> for the encrypted key.');
        }

        $inputKeyAlgo = $inputKey->getAlgorith();
        if ($symmetricKeyInfo->isEncrypted) {
            $symKeyInfoAlgo = $symmetricKeyInfo->getAlgorith();

            if (in_array($symKeyInfoAlgo, $blacklist, TRUE)) {
                throw new Exception('Algorithm disabled: ' . var_export($symKeyInfoAlgo, TRUE));
            }

            if ($symKeyInfoAlgo === XMLSecurityKey::RSA_OAEP_MGF1P && $inputKeyAlgo === XMLSecurityKey::RSA_1_5) {
                /*
                 * The RSA key formats are equal, so loading an RSA_1_5 key
                 * into an RSA_OAEP_MGF1P key can be done without problems.
                 * We therefore pretend that the input key is an
                 * RSA_OAEP_MGF1P key.
                 */
                $inputKeyAlgo = XMLSecurityKey::RSA_OAEP_MGF1P;
            }

            /* Make sure that the input key format is the same as the one used to encrypt the key. */
            if ($inputKeyAlgo !== $symKeyInfoAlgo) {
                throw new Exception(
                    'Algorithm mismatch between input key and key used to encrypt ' .
                    ' the symmetric key for the message. Key was: ' .
                    var_export($inputKeyAlgo, TRUE) . '; message was: ' .
                    var_export($symKeyInfoAlgo, TRUE)
                );
            }

            /** @var XMLSecEnc $encKey */
            $encKey = $symmetricKeyInfo->encryptedCtx;
            $symmetricKeyInfo->key = $inputKey->key;

            $keySize = $symmetricKey->getSymmetricKeySize();
            if ($keySize === NULL) {
                /* To protect against "key oracle" attacks, we need to be able to create a
                 * symmetric key, and for that we need to know the key size.
                 */
                throw new Exception('Unknown key size for encryption algorithm: ' . var_export($symmetricKey->type, TRUE));
            }

            try {
                $key = $encKey->decryptKey($symmetricKeyInfo);
                if (strlen($key) != $keySize) {
                    throw new Exception(
                        'Unexpected key size (' . strlen($key) * 8 . 'bits) for encryption algorithm: ' .
                        var_export($symmetricKey->type, TRUE)
                    );
                }
            } catch (Exception $e) {
                /* We failed to decrypt this key. Log it, and substitute a "random" key. */
                SAML2_Utils::getContainer()->getLogger()->error('Failed to decrypt symmetric key: ' . $e->getMessage());
                /* Create a replacement key, so that it looks like we fail in the same way as if the key was correctly padded. */

                /* We base the symmetric key on the encrypted key and private key, so that we always behave the
                 * same way for a given input key.
                 */
                $encryptedKey = $encKey->getCipherValue();
                $pkey = openssl_pkey_get_details($symmetricKeyInfo->key);
                $pkey = sha1(serialize($pkey), TRUE);
                $key = sha1($encryptedKey . $pkey, TRUE);

                /* Make sure that the key has the correct length. */
                if (strlen($key) > $keySize) {
                    $key = substr($key, 0, $keySize);
                } elseif (strlen($key) < $keySize) {
                    $key = str_pad($key, $keySize);
                }
            }
            $symmetricKey->loadkey($key);

        } else {
            $symKeyAlgo = $symmetricKey->getAlgorith();
            /* Make sure that the input key has the correct format. */
            if ($inputKeyAlgo !== $symKeyAlgo) {
                throw new Exception(
                    'Algorithm mismatch between input key and key in message. ' .
                    'Key was: ' . var_export($inputKeyAlgo, TRUE) . '; message was: ' .
                    var_export($symKeyAlgo, TRUE)
                );
            }
            $symmetricKey = $inputKey;
        }

        $algorithm = $symmetricKey->getAlgorith();
        if (in_array($algorithm, $blacklist, TRUE)) {
            throw new Exception('Algorithm disabled: ' . var_export($algorithm, TRUE));
        }

        /** @var string $decrypted */
        $decrypted = $enc->decryptNode($symmetricKey, FALSE);

        /*
         * This is a workaround for the case where only a subset of the XML
         * tree was serialized for encryption. In that case, we may miss the
         * namespaces needed to parse the XML.
         */
        $xml = '<root xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '.
                     'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' .
            $decrypted .
            '</root>';

        try {
            $newDoc = SAML2_DOMDocumentFactory::fromString($xml);
        } catch (SAML2_Exception_RuntimeException $e) {
            throw new Exception('Failed to parse decrypted XML. Maybe the wrong sharedkey was used?', 0, $e);
        }

        $decryptedElement = $newDoc->firstChild->firstChild;
        if ($decryptedElement === NULL) {
            throw new Exception('Missing encrypted element.');
        }

        if (!($decryptedElement instanceof DOMElement)) {
            throw new Exception('Decrypted element was not actually a DOMElement.');
        }

        return $decryptedElement;
    }

    /**
     * Decrypt an encrypted element.
     *
     * @param  DOMElement     $encryptedData The encrypted data.
     * @param  XMLSecurityKey $inputKey      The decryption key.
     * @param  array          $blacklist     Blacklisted decryption algorithms.
     * @return DOMElement     The decrypted element.
     * @throws Exception
     */
    public static function decryptElement(DOMElement $encryptedData, XMLSecurityKey $inputKey, array $blacklist = array())
    {
        try {
            return self::doDecryptElement($encryptedData, $inputKey, $blacklist);
        } catch (Exception $e) {
            /*
             * Something went wrong during decryption, but for security
             * reasons we cannot tell the user what failed.
             */
            SAML2_Utils::getContainer()->getLogger()->error('Decryption failed: ' . $e->getMessage());
            throw new Exception('Failed to decrypt XML element.', 0, $e);
        }
    }

    /**
     * Extract localized strings from a set of nodes.
     *
     * @param  DOMElement $parent       The element that contains the localized strings.
     * @param  string     $namespaceURI The namespace URI the localized strings should have.
     * @param  string     $localName    The localName of the localized strings.
     * @return array      Localized strings.
     */
    public static function extractLocalizedStrings(DOMElement $parent, $namespaceURI, $localName)
    {
        assert('is_string($namespaceURI)');
        assert('is_string($localName)');

        $ret = array();
        for ($node = $parent->firstChild; $node !== NULL; $node = $node->nextSibling) {
            if ($node->namespaceURI !== $namespaceURI || $node->localName !== $localName) {
                continue;
            }

            if ($node->hasAttribute('xml:lang')) {
                $language = $node->getAttribute('xml:lang');
            } else {
                $language = 'en';
            }
            $ret[$language] = trim($node->textContent);
        }

        return $ret;
    }

    /**
     * Extract strings from a set of nodes.
     *
     * @param  DOMElement $parent       The element that contains the localized strings.
     * @param  string     $namespaceURI The namespace URI the string elements should have.
     * @param  string     $localName    The localName of the string elements.
     * @return array      The string values of the various nodes.
     */
    public static function extractStrings(DOMElement $parent, $namespaceURI, $localName)
    {
        assert('is_string($namespaceURI)');
        assert('is_string($localName)');

        $ret = array();
        for ($node = $parent->firstChild; $node !== NULL; $node = $node->nextSibling) {
            if ($node->namespaceURI !== $namespaceURI || $node->localName !== $localName) {
                continue;
            }
            $ret[] = trim($node->textContent);
        }

        return $ret;
    }

    /**
     * Append string element.
     *
     * @param  DOMElement $parent    The parent element we should append the new nodes to.
     * @param  string     $namespace The namespace of the created element.
     * @param  string     $name      The name of the created element.
     * @param  string     $value     The value of the element.
     * @return DOMElement The generated element.
     */
    public static function addString(DOMElement $parent, $namespace, $name, $value)
    {
        assert('is_string($namespace)');
        assert('is_string($name)');
        assert('is_string($value)');

        $doc = $parent->ownerDocument;

        $n = $doc->createElementNS($namespace, $name);
        $n->appendChild($doc->createTextNode($value));
        $parent->appendChild($n);

        return $n;
    }

    /**
     * Append string elements.
     *
     * @param DOMElement $parent    The parent element we should append the new nodes to.
     * @param string     $namespace The namespace of the created elements
     * @param string     $name      The name of the created elements
     * @param bool       $localized Whether the strings are localized, and should include the xml:lang attribute.
     * @param array      $values    The values we should create the elements from.
     */
    public static function addStrings(DOMElement $parent, $namespace, $name, $localized, array $values)
    {
        assert('is_string($namespace)');
        assert('is_string($name)');
        assert('is_bool($localized)');

        $doc = $parent->ownerDocument;

        foreach ($values as $index => $value) {
            $n = $doc->createElementNS($namespace, $name);
            $n->appendChild($doc->createTextNode($value));
            if ($localized) {
                $n->setAttribute('xml:lang', $index);
            }
            $parent->appendChild($n);
        }
    }

    /**
     * Create a KeyDescriptor with the given certificate.
     *
     * @param  string                     $x509Data The certificate, as a base64-encoded DER data.
     * @return SAML2_XML_md_KeyDescriptor The keydescriptor.
     */
    public static function createKeyDescriptor($x509Data)
    {
        assert('is_string($x509Data)');

        $x509Certificate = new SAML2_XML_ds_X509Certificate();
        $x509Certificate->certificate = $x509Data;

        $x509Data = new SAML2_XML_ds_X509Data();
        $x509Data->data[] = $x509Certificate;

        $keyInfo = new SAML2_XML_ds_KeyInfo();
        $keyInfo->info[] = $x509Data;

        $keyDescriptor = new SAML2_XML_md_KeyDescriptor();
        $keyDescriptor->KeyInfo = $keyInfo;

        return $keyDescriptor;
    }

    /**
     * This function converts a SAML2 timestamp on the form
     * yyyy-mm-ddThh:mm:ss(\.s+)?Z to a UNIX timestamp. The sub-second
     * part is ignored.
     *
     * Andreas comments:
     *  I got this timestamp from Shibboleth 1.3 IdP: 2008-01-17T11:28:03.577Z
     *  Therefore I added to possibility to have microseconds to the format.
     * Added: (\.\\d{1,3})? to the regex.
     *
     * Note that we always require a 'Z' timezone for the dateTime to be valid.
     * This is not in the SAML spec but that's considered to be a bug in the
     * spec. See https://github.com/simplesamlphp/saml2/pull/36 for some
     * background.
     *
     * @param string $time The time we should convert.
     * @return int Converted to a unix timestamp.
     * @throws Exception
     */
    public static function xsDateTimeToTimestamp($time)
    {
        $matches = array();

        // We use a very strict regex to parse the timestamp.
        $regex = '/^(\\d\\d\\d\\d)-(\\d\\d)-(\\d\\d)T(\\d\\d):(\\d\\d):(\\d\\d)(?:\\.\\d+)?Z$/D';
        if (preg_match($regex, $time, $matches) == 0) {
            throw new Exception(
                'Invalid SAML2 timestamp passed to xsDateTimeToTimestamp: ' . $time
            );
        }

        // Extract the different components of the time from the  matches in the regex.
        // intval will ignore leading zeroes in the string.
        $year   = intval($matches[1]);
        $month  = intval($matches[2]);
        $day    = intval($matches[3]);
        $hour   = intval($matches[4]);
        $minute = intval($matches[5]);
        $second = intval($matches[6]);

        // We use gmmktime because the timestamp will always be given
        //in UTC.
        $ts = gmmktime($hour, $minute, $second, $month, $day, $year);

        return $ts;
    }

    /**
     * @return SAML2_Compat_Ssp_Container
     */
    public static function getContainer()
    {
        return SAML2_Compat_ContainerSingleton::getInstance();
    }
}
