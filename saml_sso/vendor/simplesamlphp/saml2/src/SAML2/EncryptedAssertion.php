<?php

/**
 * Class handling encrypted assertions.
 *
 * @package SimpleSAMLphp
 */
class SAML2_EncryptedAssertion
{
    /**
     * The current encrypted assertion.
     *
     * @var DOMElement
     */
    private $encryptedData;

    /**
     * Constructor for SAML 2 encrypted assertions.
     *
     * @param DOMElement|NULL $xml The encrypted assertion XML element.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        $data = SAML2_Utils::xpQuery($xml, './xenc:EncryptedData');
        if (count($data) === 0) {
            throw new Exception('Missing encrypted data in <saml:EncryptedAssertion>.');
        } elseif (count($data) > 1) {
            throw new Exception('More than one encrypted data element in <saml:EncryptedAssertion>.');
        }
        $this->encryptedData = $data[0];
    }

    /**
     * Set the assertion.
     *
     * @param SAML2_Assertion $assertion The assertion.
     * @param XMLSecurityKey  $key       The key we should use to encrypt the assertion.
     * @throws Exception
     */
    public function setAssertion(SAML2_Assertion $assertion, XMLSecurityKey $key)
    {
        $xml = $assertion->toXML();

        SAML2_Utils::getContainer()->debugMessage($xml, 'encrypt');

        $enc = new XMLSecEnc();
        $enc->setNode($xml);
        $enc->type = XMLSecEnc::Element;

        switch ($key->type) {
            case XMLSecurityKey::TRIPLEDES_CBC:
            case XMLSecurityKey::AES128_CBC:
            case XMLSecurityKey::AES192_CBC:
            case XMLSecurityKey::AES256_CBC:
                $symmetricKey = $key;
                break;

            case XMLSecurityKey::RSA_1_5:
            case XMLSecurityKey::RSA_OAEP_MGF1P:
                $symmetricKey = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
                $symmetricKey->generateSessionKey();

                $enc->encryptKey($key, $symmetricKey);

                break;

            default:
                throw new Exception('Unknown key type for encryption: ' . $key->type);
        }

        $this->encryptedData = $enc->encryptNode($symmetricKey);
    }

    /**
     * Retrieve the assertion.
     *
     * @param  XMLSecurityKey  $inputKey  The key we should use to decrypt the assertion.
     * @param  array           $blacklist Blacklisted decryption algorithms.
     * @return SAML2_Assertion The decrypted assertion.
     */
    public function getAssertion(XMLSecurityKey $inputKey, array $blacklist = array())
    {
        $assertionXML = SAML2_Utils::decryptElement($this->encryptedData, $inputKey, $blacklist);

        SAML2_Utils::getContainer()->debugMessage($assertionXML, 'decrypt');

        return new SAML2_Assertion($assertionXML);
    }

    /**
     * Convert this encrypted assertion to an XML element.
     *
     * @param  DOMNode|NULL $parentElement The DOM node the assertion should be created in.
     * @return DOMElement   This encrypted assertion.
     */
    public function toXML(DOMNode $parentElement = NULL)
    {
        if ($parentElement === NULL) {
            $document = SAML2_DOMDocumentFactory::create();
            $parentElement = $document;
        } else {
            $document = $parentElement->ownerDocument;
        }

        $root = $document->createElementNS(SAML2_Const::NS_SAML, 'saml:' . 'EncryptedAssertion');
        $parentElement->appendChild($root);

        $root->appendChild($document->importNode($this->encryptedData, TRUE));

        return $root;
    }

}
