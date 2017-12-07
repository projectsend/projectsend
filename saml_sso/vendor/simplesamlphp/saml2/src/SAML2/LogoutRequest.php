<?php

/**
 * Class for SAML 2 logout request messages.
 *
 * @package SimpleSAMLphp
 */
class SAML2_LogoutRequest extends SAML2_Request
{
    /**
     * The expiration time of this request.
     *
     * @var int|NULL
     */
    private $notOnOrAfter;

    /**
     * The encrypted NameID in the request.
     *
     * If this is not NULL, the NameID needs decryption before it can be accessed.
     *
     * @var DOMElement|NULL
     */
    private $encryptedNameId;

    /**
     * The name identifier of the session that should be terminated.
     *
     * @var array
     */
    private $nameId;

    /**
     * The SessionIndexes of the sessions that should be terminated.
     *
     * @var array
     */
    private $sessionIndexes;

    /**
     * Constructor for SAML 2 logout request messages.
     *
     * @param DOMElement|NULL $xml The input message.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('LogoutRequest', $xml);

        $this->sessionIndexes = array();

        if ($xml === NULL) {
            return;
        }

        if ($xml->hasAttribute('NotOnOrAfter')) {
            $this->notOnOrAfter = SAML2_Utils::xsDateTimeToTimestamp($xml->getAttribute('NotOnOrAfter'));
        }

        $nameId = SAML2_Utils::xpQuery($xml, './saml_assertion:NameID | ./saml_assertion:EncryptedID/xenc:EncryptedData');
        if (empty($nameId)) {
            throw new Exception('Missing <saml:NameID> or <saml:EncryptedID> in <samlp:LogoutRequest>.');
        } elseif (count($nameId) > 1) {
            throw new Exception('More than one <saml:NameID> or <saml:EncryptedD> in <samlp:LogoutRequest>.');
        }
        $nameId = $nameId[0];
        if ($nameId->localName === 'EncryptedData') {
            /* The NameID element is encrypted. */
            $this->encryptedNameId = $nameId;
        } else {
            $this->nameId = SAML2_Utils::parseNameId($nameId);
        }

        $sessionIndexes = SAML2_Utils::xpQuery($xml, './saml_protocol:SessionIndex');
        foreach ($sessionIndexes as $sessionIndex) {
            $this->sessionIndexes[] = trim($sessionIndex->textContent);
        }
    }

    /**
     * Retrieve the expiration time of this request.
     *
     * @return int|NULL The expiration time of this request.
     */
    public function getNotOnOrAfter()
    {
        return $this->notOnOrAfter;
    }

    /**
     * Set the expiration time of this request.
     *
     * @param int|NULL $notOnOrAfter The expiration time of this request.
     */
    public function setNotOnOrAfter($notOnOrAfter)
    {
        assert('is_int($notOnOrAfter) || is_null($notOnOrAfter)');

        $this->notOnOrAfter = $notOnOrAfter;
    }

    /**
     * Check whether the NameId is encrypted.
     *
     * @return TRUE if the NameId is encrypted, FALSE if not.
     */
    public function isNameIdEncrypted()
    {
        if ($this->encryptedNameId !== NULL) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Encrypt the NameID in the LogoutRequest.
     *
     * @param XMLSecurityKey $key The encryption key.
     */
    public function encryptNameId(XMLSecurityKey $key)
    {
        /* First create a XML representation of the NameID. */
        $doc = SAML2_DOMDocumentFactory::create();
        $root = $doc->createElement('root');
        $doc->appendChild($root);
        SAML2_Utils::addNameId($root, $this->nameId);
        $nameId = $root->firstChild;

        SAML2_Utils::getContainer()->debugMessage($nameId, 'encrypt');

        /* Encrypt the NameID. */
        $enc = new XMLSecEnc();
        $enc->setNode($nameId);
        $enc->type = XMLSecEnc::Element;

        $symmetricKey = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
        $symmetricKey->generateSessionKey();
        $enc->encryptKey($key, $symmetricKey);

        $this->encryptedNameId = $enc->encryptNode($symmetricKey);
        $this->nameId = NULL;
    }

    /**
     * Decrypt the NameID in the LogoutRequest.
     *
     * @param XMLSecurityKey $key       The decryption key.
     * @param array          $blacklist Blacklisted decryption algorithms.
     */
    public function decryptNameId(XMLSecurityKey $key, array $blacklist = array())
    {
        if ($this->encryptedNameId === NULL) {
            /* No NameID to decrypt. */

            return;
        }

        $nameId = SAML2_Utils::decryptElement($this->encryptedNameId, $key, $blacklist);
        SAML2_Utils::getContainer()->debugMessage($nameId, 'decrypt');
        $this->nameId = SAML2_Utils::parseNameId($nameId);

        $this->encryptedNameId = NULL;
    }

    /**
     * Retrieve the name identifier of the session that should be terminated.
     *
     * @return array The name identifier of the session that should be terminated.
     * @throws Exception
     */
    public function getNameId()
    {
        if ($this->encryptedNameId !== NULL) {
            throw new Exception('Attempted to retrieve encrypted NameID without decrypting it first.');
        }

        return $this->nameId;
    }

    /**
     * Set the name identifier of the session that should be terminated.
     *
     * The name identifier must be in the format accepted by SAML2_message::buildNameId().
     *
     * @see SAML2_message::buildNameId()
     * @param array $nameId The name identifier of the session that should be terminated.
     */
    public function setNameId($nameId)
    {
        assert('is_array($nameId)');

        $this->nameId = $nameId;
    }

    /**
     * Retrieve the SessionIndexes of the sessions that should be terminated.
     *
     * @return array The SessionIndexes, or an empty array if all sessions should be terminated.
     */
    public function getSessionIndexes()
    {
        return $this->sessionIndexes;
    }

    /**
     * Set the SessionIndexes of the sessions that should be terminated.
     *
     * @param array $sessionIndexes The SessionIndexes, or an empty array if all sessions should be terminated.
     */
    public function setSessionIndexes(array $sessionIndexes)
    {
        $this->sessionIndexes = $sessionIndexes;
    }

    /**
     * Retrieve the sesion index of the session that should be terminated.
     *
     * @return string|NULL The sesion index of the session that should be terminated.
     */
    public function getSessionIndex()
    {
        if (empty($this->sessionIndexes)) {
            return NULL;
        }

        return $this->sessionIndexes[0];
    }

    /**
     * Set the sesion index of the session that should be terminated.
     *
     * @param string|NULL $sessionIndex The sesion index of the session that should be terminated.
     */
    public function setSessionIndex($sessionIndex)
    {
        assert('is_string($sessionIndex) || is_null($sessionIndex)');

        if (is_null($sessionIndex)) {
            $this->sessionIndexes = array();
        } else {
            $this->sessionIndexes = array($sessionIndex);
        }
    }

    /**
     * Convert this logout request message to an XML element.
     *
     * @return DOMElement This logout request.
     */
    public function toUnsignedXML()
    {
        $root = parent::toUnsignedXML();

        if ($this->notOnOrAfter !== NULL) {
            $root->setAttribute('NotOnOrAfter', gmdate('Y-m-d\TH:i:s\Z', $this->notOnOrAfter));
        }

        if ($this->encryptedNameId === NULL) {
            SAML2_Utils::addNameId($root, $this->nameId);
        } else {
            $eid = $root->ownerDocument->createElementNS(SAML2_Const::NS_SAML, 'saml:' . 'EncryptedID');
            $root->appendChild($eid);
            $eid->appendChild($root->ownerDocument->importNode($this->encryptedNameId, TRUE));
        }

        foreach ($this->sessionIndexes as $sessionIndex) {
            SAML2_Utils::addString($root, SAML2_Const::NS_SAMLP, 'SessionIndex', $sessionIndex);
        }

        return $root;
    }

}
