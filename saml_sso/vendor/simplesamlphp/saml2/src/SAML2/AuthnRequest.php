<?php

/**
 * Class for SAML 2 authentication request messages.
 *
 * @package SimpleSAMLphp
 */
class SAML2_AuthnRequest extends SAML2_Request
{
    /**
     * The options for what type of name identifier should be returned.
     *
     * @var array
     */
    private $nameIdPolicy;

    /**
     * Whether the Identity Provider must authenticate the user again.
     *
     * @var bool
     */
    private $forceAuthn;


    /**
     * Optional ProviderID attribute
     *
     * @var string
     */
    private $ProviderName;


    /**
     * Set to TRUE if this request is passive.
     *
     * @var bool.
     */
    private $isPassive;

    /**
     * The list of providerIDs in this request's scoping element
     *
     * @var array
     */
    private $IDPList = array();

    /**
     * The ProxyCount in this request's scoping element
     *
     * @var int
     */
    private $ProxyCount = NULL;

    /**
     * The RequesterID list in this request's scoping element
     *
     * @var array
     */

    private $RequesterID = array();

    /**
     * The URL of the asertion consumer service where the response should be delivered.
     *
     * @var string|NULL
     */
    private $assertionConsumerServiceURL;


    /**
     * What binding should be used when sending the response.
     *
     * @var string|NULL
     */
    private $protocolBinding;


    /**
     * The index of the AttributeConsumingService.
     *
     * @var int|NULL
     */
    private $attributeConsumingServiceIndex;

    /**
     * The index of the AssertionConsumerService.
     *
     * @var int|NULL
     */
    private $assertionConsumerServiceIndex;


    /**
     * What authentication context was requested.
     *
     * Array with the following elements.
     * - AuthnContextClassRef (required)
     * - Comparison (optinal)
     *
     * @var array
     */
    private $requestedAuthnContext;

    /**
     * @var SAML2_XML_saml_SubjectConfirmation[]
     */
    private $subjectConfirmation = array();

    /**
     * @var string
     */
    private $encryptedNameId;

    /**
     * @var string
     */
    private $nameId;

    /**
     * Constructor for SAML 2 authentication request messages.
     *
     * @param DOMElement|NULL $xml The input message.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('AuthnRequest', $xml);

        $this->nameIdPolicy = array();
        $this->forceAuthn = FALSE;
        $this->isPassive = FALSE;

        if ($xml === NULL) {
            return;
        }

        $this->forceAuthn = SAML2_Utils::parseBoolean($xml, 'ForceAuthn', FALSE);
        $this->isPassive = SAML2_Utils::parseBoolean($xml, 'IsPassive', FALSE);

        if ($xml->hasAttribute('AssertionConsumerServiceURL')) {
            $this->assertionConsumerServiceURL = $xml->getAttribute('AssertionConsumerServiceURL');
        }

        if ($xml->hasAttribute('ProtocolBinding')) {
            $this->protocolBinding = $xml->getAttribute('ProtocolBinding');
        }

        if ($xml->hasAttribute('AttributeConsumingServiceIndex')) {
            $this->attributeConsumingServiceIndex = (int) $xml->getAttribute('AttributeConsumingServiceIndex');
        }

        if ($xml->hasAttribute('AssertionConsumerServiceIndex')) {
            $this->assertionConsumerServiceIndex = (int) $xml->getAttribute('AssertionConsumerServiceIndex');
        }

        $this->parseSubject($xml);
        $this->parseNameIdPolicy($xml);
        $this->parseRequestedAuthnContext($xml);
        $this->parseScoping($xml);
    }

    /**
     * @param $xml
     *
     * @throws Exception
     */
    private function parseSubject(DOMElement $xml)
    {
        $subject = SAML2_Utils::xpQuery($xml, './saml_assertion:Subject');
        if (empty($subject)) {
            return;
        }

        if (count($subject) > 1) {
            throw new Exception('More than one <saml:Subject> in <saml:AuthnRequest>.');
        }
        $subject = $subject[0];

        $nameId = SAML2_Utils::xpQuery(
            $subject,
            './saml_assertion:NameID | ./saml_assertion:EncryptedID/xenc:EncryptedData'
        );
        if (empty($nameId)) {
            throw new Exception('Missing <saml:NameID> or <saml:EncryptedID> in <saml:Subject>.');
        } elseif (count($nameId) > 1) {
            throw new Exception('More than one <saml:NameID> or <saml:EncryptedID> in <saml:Subject>.');
        }
        $nameId = $nameId[0];
        if ($nameId->localName === 'EncryptedData') {
            /* The NameID element is encrypted. */
            $this->encryptedNameId = $nameId;
        } else {
            $this->nameId = SAML2_Utils::parseNameId($nameId);
        }

        $subjectConfirmation = SAML2_Utils::xpQuery($subject, './saml_assertion:SubjectConfirmation');
        foreach ($subjectConfirmation as $sc) {
            $this->subjectConfirmation[] = new SAML2_XML_saml_SubjectConfirmation($sc);
        }
    }

    /**
     * @param DOMElement $xml
     *
     * @throws Exception
     */
    protected function parseNameIdPolicy(DOMElement $xml)
    {
        $nameIdPolicy = SAML2_Utils::xpQuery($xml, './saml_protocol:NameIDPolicy');
        if (empty($nameIdPolicy)) {
            return;
        }

        $nameIdPolicy = $nameIdPolicy[0];
        if ($nameIdPolicy->hasAttribute('Format')) {
            $this->nameIdPolicy['Format'] = $nameIdPolicy->getAttribute('Format');
        }
        if ($nameIdPolicy->hasAttribute('SPNameQualifier')) {
            $this->nameIdPolicy['SPNameQualifier'] = $nameIdPolicy->getAttribute('SPNameQualifier');
        }
        if ($nameIdPolicy->hasAttribute('AllowCreate')) {
            $this->nameIdPolicy['AllowCreate'] = SAML2_Utils::parseBoolean($nameIdPolicy, 'AllowCreate', FALSE);
        }
    }

    /**
     * @param DOMElement $xml
     */
    protected function parseRequestedAuthnContext(DOMElement $xml)
    {
        $requestedAuthnContext = SAML2_Utils::xpQuery($xml, './saml_protocol:RequestedAuthnContext');
        if (empty($requestedAuthnContext)) {
            return;
        }

        $requestedAuthnContext = $requestedAuthnContext[0];

        $rac = array(
            'AuthnContextClassRef' => array(),
            'Comparison'           => SAML2_Const::COMPARISON_EXACT,
        );

        $accr = SAML2_Utils::xpQuery($requestedAuthnContext, './saml_assertion:AuthnContextClassRef');
        foreach ($accr as $i) {
            $rac['AuthnContextClassRef'][] = trim($i->textContent);
        }

        if ($requestedAuthnContext->hasAttribute('Comparison')) {
            $rac['Comparison'] = $requestedAuthnContext->getAttribute('Comparison');
        }

        $this->requestedAuthnContext = $rac;
    }

    /**
     * @param DOMElement $xml
     *
     * @throws Exception
     */
    protected function parseScoping(DOMElement $xml)
    {
        $scoping = SAML2_Utils::xpQuery($xml, './saml_protocol:Scoping');
        if (empty($scoping)) {
            return;
        }

        $scoping = $scoping[0];

        if ($scoping->hasAttribute('ProxyCount')) {
            $this->ProxyCount = (int) $scoping->getAttribute('ProxyCount');
        }
        $idpEntries = SAML2_Utils::xpQuery($scoping, './saml_protocol:IDPList/saml_protocol:IDPEntry');

        foreach ($idpEntries as $idpEntry) {
            if (!$idpEntry->hasAttribute('ProviderID')) {
                throw new Exception("Could not get ProviderID from Scoping/IDPEntry element in AuthnRequest object");
            }
            $this->IDPList[] = $idpEntry->getAttribute('ProviderID');
        }

        $requesterIDs = SAML2_Utils::xpQuery($scoping, './saml_protocol:RequesterID');
        foreach ($requesterIDs as $requesterID) {
            $this->RequesterID[] = trim($requesterID->textContent);
        }
    }

    /**
     * Retrieve the NameIdPolicy.
     *
     * @see SAML2_AuthnRequest::setNameIdPolicy()
     * @return array The NameIdPolicy.
     */
    public function getNameIdPolicy()
    {
        return $this->nameIdPolicy;
    }


    /**
     * Set the NameIDPolicy.
     *
     * This function accepts an array with the following options:
     *  - 'Format'
     *  - 'SPNameQualifier'
     *  - 'AllowCreate'
     *
     * @param array $nameIdPolicy The NameIDPolicy.
     */
    public function setNameIdPolicy(array $nameIdPolicy)
    {
        $this->nameIdPolicy = $nameIdPolicy;
    }


    /**
     * Retrieve the value of the ForceAuthn attribute.
     *
     * @return bool The ForceAuthn attribute.
     */
    public function getForceAuthn()
    {
        return $this->forceAuthn;
    }


    /**
     * Set the value of the ForceAuthn attribute.
     *
     * @param bool $forceAuthn The ForceAuthn attribute.
     */
    public function setForceAuthn($forceAuthn)
    {
        assert('is_bool($forceAuthn)');

        $this->forceAuthn = $forceAuthn;
    }


    /**
     * Retrieve the value of the ProviderName attribute.
     *
     * @return string The ProviderName attribute.
     */
    public function getProviderName()
    {
        return $this->ProviderName;
    }


    /**
     * Set the value of the ProviderName attribute.
     *
     * @param string $ProviderName The ProviderName attribute.
     */
    public function setProviderName($ProviderName)
    {
        assert('is_string($ProviderName)');

        $this->ProviderName = $ProviderName;
    }


    /**
     * Retrieve the value of the IsPassive attribute.
     *
     * @return bool The IsPassive attribute.
     */
    public function getIsPassive()
    {
        return $this->isPassive;
    }


    /**
     * Set the value of the IsPassive attribute.
     *
     * @param bool $isPassive The IsPassive attribute.
     */
    public function setIsPassive($isPassive)
    {
        assert('is_bool($isPassive)');

        $this->isPassive = $isPassive;
    }


    /**
     * This function sets the scoping for the request.
     * See Core 3.4.1.2 for the definition of scoping.
     * Currently we support an IDPList of idpEntries.
     *
     * Each idpEntries consists of an array, containing
     * keys (mapped to attributes) and corresponding values.
     * Allowed attributes: Loc, Name, ProviderID.
     *
     * For backward compatibility, an idpEntries can also
     * be a string instead of an array, where each string
     * is mapped to the value of attribute ProviderID.
     */
    public function setIDPList($IDPList)
    {
        assert('is_array($IDPList)');
        $this->IDPList = $IDPList;
    }


    /**
     * This function retrieves the list of providerIDs from this authentication request.
     * Currently we only support a list of ipd ientity id's.
     * @return array List of idp EntityIDs from the request
     */
    public function getIDPList()
    {
        return $this->IDPList;
    }

    /**
     * @param int $ProxyCount
     */
    public function setProxyCount($ProxyCount)
    {
        assert('is_int($ProxyCount)');
        $this->ProxyCount = $ProxyCount;
    }

    /**
     * @return int
     */
    public function getProxyCount()
    {
        return $this->ProxyCount;
    }

    /**
     * @param array $RequesterID
     */
    public function setRequesterID(array $RequesterID)
    {
        $this->RequesterID = $RequesterID;
    }

    /**
     * @return array
     */
    public function getRequesterID()
    {
        return $this->RequesterID;
    }

    /**
     * Retrieve the value of the AssertionConsumerServiceURL attribute.
     *
     * @return string|NULL The AssertionConsumerServiceURL attribute.
     */
    public function getAssertionConsumerServiceURL()
    {
        return $this->assertionConsumerServiceURL;
    }

    /**
     * Set the value of the AssertionConsumerServiceURL attribute.
     *
     * @param string|NULL $assertionConsumerServiceURL The AssertionConsumerServiceURL attribute.
     */
    public function setAssertionConsumerServiceURL($assertionConsumerServiceURL)
    {
        assert('is_string($assertionConsumerServiceURL) || is_null($assertionConsumerServiceURL)');

        $this->assertionConsumerServiceURL = $assertionConsumerServiceURL;
    }

    /**
     * Retrieve the value of the ProtocolBinding attribute.
     *
     * @return string|NULL The ProtocolBinding attribute.
     */
    public function getProtocolBinding()
    {
        return $this->protocolBinding;
    }

    /**
     * Set the value of the ProtocolBinding attribute.
     *
     * @param string $protocolBinding The ProtocolBinding attribute.
     */
    public function setProtocolBinding($protocolBinding)
    {
        assert('is_string($protocolBinding) || is_null($protocolBinding)');

        $this->protocolBinding = $protocolBinding;
    }

    /**
     * Retrieve the value of the AttributeConsumingServiceIndex attribute.
     *
     * @return int|NULL The AttributeConsumingServiceIndex attribute.
     */
    public function getAttributeConsumingServiceIndex()
    {
        return $this->attributeConsumingServiceIndex;
    }

    /**
     * Set the value of the AttributeConsumingServiceIndex attribute.
     *
     * @param int|NULL $attributeConsumingServiceIndex The AttributeConsumingServiceIndex attribute.
     */
    public function setAttributeConsumingServiceIndex($attributeConsumingServiceIndex)
    {
        assert('is_int($attributeConsumingServiceIndex) || is_null($attributeConsumingServiceIndex)');

        $this->attributeConsumingServiceIndex = $attributeConsumingServiceIndex;
    }

    /**
     * Retrieve the value of the AssertionConsumerServiceIndex attribute.
     *
     * @return int|NULL The AssertionConsumerServiceIndex attribute.
     */
    public function getAssertionConsumerServiceIndex()
    {
        return $this->assertionConsumerServiceIndex;
    }

    /**
     * Set the value of the AssertionConsumerServiceIndex attribute.
     *
     * @param int|NULL $assertionConsumerServiceIndex The AssertionConsumerServiceIndex attribute.
     */
    public function setAssertionConsumerServiceIndex($assertionConsumerServiceIndex)
    {
        assert('is_int($assertionConsumerServiceIndex) || is_null($assertionConsumerServiceIndex)');

        $this->assertionConsumerServiceIndex = $assertionConsumerServiceIndex;
    }

    /**
     * Retrieve the RequestedAuthnContext.
     *
     * @return array|NULL The RequestedAuthnContext.
     */
    public function getRequestedAuthnContext()
    {
        return $this->requestedAuthnContext;
    }

    /**
     * Set the RequestedAuthnContext.
     *
     * @param array|NULL $requestedAuthnContext The RequestedAuthnContext.
     */
    public function setRequestedAuthnContext($requestedAuthnContext)
    {
        assert('is_array($requestedAuthnContext) || is_null($requestedAuthnContext)');

        $this->requestedAuthnContext = $requestedAuthnContext;
    }

    /**
     * Retrieve the NameId of the subject in the assertion.
     *
     * The returned NameId is in the format used by SAML2_Utils::addNameId().
     *
     * @see SAML2_Utils::addNameId()
     * @return array|NULL The name identifier of the assertion.
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
     * Set the NameId of the subject in the assertion.
     *
     * The NameId must be in the format accepted by SAML2_Utils::addNameId().
     *
     * @see SAML2_Utils::addNameId()
     *
     * @param array|NULL $nameId The name identifier of the assertion.
     */
    public function setNameId($nameId)
    {
        assert('is_array($nameId) || is_null($nameId)');

        $this->nameId = $nameId;
    }

    /**
     * Encrypt the NameID in the AuthnRequest.
     *
     * @param XMLSecurityKey $key The encryption key.
     */
    public function encryptNameId(XMLSecurityKey $key)
    {
        /* First create a XML representation of the NameID. */
        $doc  = new DOMDocument();
        $root = $doc->createElement('root');
        $doc->appendChild($root);
        SAML2_Utils::addNameId($root, $this->nameId);
        $nameId = $root->firstChild;

        SAML2_Utils::getContainer()->debugMessage($nameId, 'encrypt');

        /* Encrypt the NameID. */
        $enc = new XMLSecEnc();
        $enc->setNode($nameId);
        // @codingStandardsIgnoreStart
        $enc->type = XMLSecEnc::Element;
        // @codingStandardsIgnoreEnd

        $symmetricKey = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
        $symmetricKey->generateSessionKey();
        $enc->encryptKey($key, $symmetricKey);

        $this->encryptedNameId = $enc->encryptNode($symmetricKey);
        $this->nameId          = NULL;
    }

    /**
     * Decrypt the NameId of the subject in the assertion.
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
     * Retrieve the SubjectConfirmation elements we have in our Subject element.
     *
     * @return SAML2_XML_saml_SubjectConfirmation[]
     */
    public function getSubjectConfirmation()
    {
        return $this->subjectConfirmation;
    }

    /**
     * Set the SubjectConfirmation elements that should be included in the assertion.
     *
     * @param array SAML2_XML_saml_SubjectConfirmation[]
     */
    public function setSubjectConfirmation(array $subjectConfirmation)
    {
        $this->subjectConfirmation = $subjectConfirmation;
    }

    /**
     * Convert this authentication request to an XML element.
     *
     * @return DOMElement This authentication request.
     */
    public function toUnsignedXML()
    {
        $root = parent::toUnsignedXML();

        if ($this->forceAuthn) {
            $root->setAttribute('ForceAuthn', 'true');
        }

        if ($this->ProviderName !== NULL) {
            $root->setAttribute('ProviderName', $this->ProviderName);
        }

        if ($this->isPassive) {
            $root->setAttribute('IsPassive', 'true');
        }

        if ($this->assertionConsumerServiceIndex !== NULL) {
            $root->setAttribute('AssertionConsumerServiceIndex', $this->assertionConsumerServiceIndex);
        } else {
            if ($this->assertionConsumerServiceURL !== NULL) {
                $root->setAttribute('AssertionConsumerServiceURL', $this->assertionConsumerServiceURL);
            }
            if ($this->protocolBinding !== NULL) {
                $root->setAttribute('ProtocolBinding', $this->protocolBinding);
            }
        }

        if ($this->attributeConsumingServiceIndex !== NULL) {
            $root->setAttribute('AttributeConsumingServiceIndex', $this->attributeConsumingServiceIndex);
        }

        $this->addSubject($root);

        if (!empty($this->nameIdPolicy)) {
            $nameIdPolicy = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'NameIDPolicy');
            if (array_key_exists('Format', $this->nameIdPolicy)) {
                $nameIdPolicy->setAttribute('Format', $this->nameIdPolicy['Format']);
            }
            if (array_key_exists('SPNameQualifier', $this->nameIdPolicy)) {
                $nameIdPolicy->setAttribute('SPNameQualifier', $this->nameIdPolicy['SPNameQualifier']);
            }
            if (array_key_exists('AllowCreate', $this->nameIdPolicy) && $this->nameIdPolicy['AllowCreate']) {
                $nameIdPolicy->setAttribute('AllowCreate', 'true');
            }
            $root->appendChild($nameIdPolicy);
        }

        $rac = $this->requestedAuthnContext;
        if (!empty($rac) && !empty($rac['AuthnContextClassRef'])) {
            $e = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'RequestedAuthnContext');
            $root->appendChild($e);
            if (isset($rac['Comparison']) && $rac['Comparison'] !== SAML2_Const::COMPARISON_EXACT) {
                $e->setAttribute('Comparison', $rac['Comparison']);
            }
            foreach ($rac['AuthnContextClassRef'] as $accr) {
                SAML2_Utils::addString($e, SAML2_Const::NS_SAML, 'AuthnContextClassRef', $accr);
            }
        }

        if ($this->ProxyCount !== NULL || count($this->IDPList) > 0 || count($this->RequesterID) > 0) {
            $scoping = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'Scoping');
            $root->appendChild($scoping);
            if ($this->ProxyCount !== NULL) {
                $scoping->setAttribute('ProxyCount', $this->ProxyCount);
            }
            if (count($this->IDPList) > 0) {
                $idplist = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'IDPList');
                foreach ($this->IDPList as $provider) {
                    $idpEntry = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'IDPEntry');
                    if (is_string($provider)) {
                        $idpEntry->setAttribute('ProviderID', $provider);
                    } elseif (is_array($provider)) {
                        foreach ($provider as $attribute => $value) {
                            if (in_array($attribute, array(
                                'ProviderID',
                                'Loc',
                                'Name'
                            ))) {
                                $idpEntry->setAttribute($attribute, $value);
                            }
                        }
                    }
                    $idplist->appendChild($idpEntry);
                }
                $scoping->appendChild($idplist);
            }
            if (count($this->RequesterID) > 0) {
                SAML2_Utils::addStrings($scoping, SAML2_Const::NS_SAMLP, 'RequesterID', FALSE, $this->RequesterID);
            }
        }

        return $root;
    }

    /**
     * Add a Subject-node to the assertion.
     *
     * @param DOMElement $root The assertion element we should add the subject to.
     */
    private function addSubject(DOMElement $root)
    {
        // If there is no nameId (encrypted or not) there is nothing to create a subject for
        if ($this->nameId === NULL && $this->encryptedNameId === NULL) {
            return;
        }

        $subject = $root->ownerDocument->createElementNS(SAML2_Const::NS_SAML, 'saml:Subject');
        $root->appendChild($subject);

        if ($this->encryptedNameId === NULL) {
            SAML2_Utils::addNameId($subject, $this->nameId);
        } else {
            $eid = $subject->ownerDocument->createElementNS(SAML2_Const::NS_SAML, 'saml:EncryptedID');
            $eid->appendChild($subject->ownerDocument->importNode($this->encryptedNameId, TRUE));
            $subject->appendChild($eid);
        }

        foreach ($this->subjectConfirmation as $sc) {
            $sc->toXML($subject);
        }
    }
}
