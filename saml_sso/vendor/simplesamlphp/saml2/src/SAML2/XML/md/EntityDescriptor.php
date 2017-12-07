<?php

/**
 * Class representing SAML 2 EntityDescriptor element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_EntityDescriptor extends SAML2_SignedElementHelper
{
    /**
     * The entityID this EntityDescriptor represents.
     *
     * @var string
     */
    public $entityID;

    /**
     * The ID of this element.
     *
     * @var string|NULL
     */
    public $ID;

    /**
     * How long this element is valid, as a unix timestamp.
     *
     * @var int|NULL
     */
    public $validUntil;

    /**
     * The length of time this element can be cached, as string.
     *
     * @var string|NULL
     */
    public $cacheDuration;

    /**
     * Extensions on this element.
     *
     * Array of extension elements.
     *
     * @var array
     */
    public $Extensions = array();

    /**
     * Array with all roles for this entity.
     *
     * Array of SAML2_XML_md_RoleDescriptor objects (and subclasses of RoleDescriptor).
     *
     * @var (SAML2_XML_md_UnknownRoleDescriptor|SAML2_XML_md_IDPSSODescriptor|SAML2_XML_md_SPSSODescriptor|SAML2_XML_md_AuthnAuthorityDescriptor|SAML2_XML_md_AttributeAuthorityDescriptor|SAML2_XML_md_PDPDescriptor)[]
     */
    public $RoleDescriptor = array();

    /**
     * AffiliationDescriptor of this entity.
     *
     * @var SAML2_XML_md_AffiliationDescriptor|NULL
     */
    public $AffiliationDescriptor = NULL;

    /**
     * Organization of this entity.
     *
     * @var SAML2_XML_md_Organization|NULL
     */
    public $Organization = NULL;

    /**
     * ContactPerson elements for this entity.
     *
     * @var SAML2_XML_md_ContactPerson[]
     */
    public $ContactPerson = array();

    /**
     * AdditionalMetadataLocation elements for this entity.
     *
     * @var SAML2_XML_md_AdditionalMetadataLocation[]
     */
    public $AdditionalMetadataLocation = array();

    /**
     * Initialize an EntitiyDescriptor.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct($xml);

        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('entityID')) {
            throw new Exception('Missing required attribute entityID on EntityDescriptor.');
        }
        $this->entityID = $xml->getAttribute('entityID');

        if ($xml->hasAttribute('ID')) {
            $this->ID = $xml->getAttribute('ID');
        }
        if ($xml->hasAttribute('validUntil')) {
            $this->validUntil = SAML2_Utils::xsDateTimeToTimestamp($xml->getAttribute('validUntil'));
        }
        if ($xml->hasAttribute('cacheDuration')) {
            $this->cacheDuration = $xml->getAttribute('cacheDuration');
        }

        $this->Extensions = SAML2_XML_md_Extensions::getList($xml);

        for ($node = $xml->firstChild; $node !== NULL; $node = $node->nextSibling) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            if ($node->namespaceURI !== SAML2_Const::NS_MD) {
                continue;
            }

            switch ($node->localName) {
                case 'RoleDescriptor':
                    $this->RoleDescriptor[] = new SAML2_XML_md_UnknownRoleDescriptor($node);
                    break;
                case 'IDPSSODescriptor':
                    $this->RoleDescriptor[] = new SAML2_XML_md_IDPSSODescriptor($node);
                    break;
                case 'SPSSODescriptor':
                    $this->RoleDescriptor[] = new SAML2_XML_md_SPSSODescriptor($node);
                    break;
                case 'AuthnAuthorityDescriptor':
                    $this->RoleDescriptor[] = new SAML2_XML_md_AuthnAuthorityDescriptor($node);
                    break;
                case 'AttributeAuthorityDescriptor':
                    $this->RoleDescriptor[] = new SAML2_XML_md_AttributeAuthorityDescriptor($node);
                    break;
                case 'PDPDescriptor':
                    $this->RoleDescriptor[] = new SAML2_XML_md_PDPDescriptor($node);
                    break;
            }
        }

        $affiliationDescriptor = SAML2_Utils::xpQuery($xml, './saml_metadata:AffiliationDescriptor');
        if (count($affiliationDescriptor) > 1) {
            throw new Exception('More than one AffiliationDescriptor in the entity.');
        } elseif (!empty($affiliationDescriptor)) {
            $this->AffiliationDescriptor = new SAML2_XML_md_AffiliationDescriptor($affiliationDescriptor[0]);
        }

        if (empty($this->RoleDescriptor) && is_null($this->AffiliationDescriptor)) {
            throw new Exception('Must have either one of the RoleDescriptors or an AffiliationDescriptor in EntityDescriptor.');
        } elseif (!empty($this->RoleDescriptor) && !is_null($this->AffiliationDescriptor)) {
            throw new Exception('AffiliationDescriptor cannot be combined with other RoleDescriptor elements in EntityDescriptor.');
        }

        $organization = SAML2_Utils::xpQuery($xml, './saml_metadata:Organization');
        if (count($organization) > 1) {
            throw new Exception('More than one Organization in the entity.');
        } elseif (!empty($organization)) {
            $this->Organization = new SAML2_XML_md_Organization($organization[0]);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:ContactPerson') as $cp) {
            $this->ContactPerson[] = new SAML2_XML_md_ContactPerson($cp);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AdditionalMetadataLocation') as $aml) {
            $this->AdditionalMetadataLocation[] = new SAML2_XML_md_AdditionalMetadataLocation($aml);
        }
    }

    /**
     * Create this EntityDescriptor.
     *
     * @param DOMElement|NULL $parent The EntitiesDescriptor we should append this EntityDescriptor to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent = NULL)
    {
        assert('is_string($this->entityID)');
        assert('is_null($this->ID) || is_string($this->ID)');
        assert('is_null($this->validUntil) || is_int($this->validUntil)');
        assert('is_null($this->cacheDuration) || is_string($this->cacheDuration)');
        assert('is_array($this->Extensions)');
        assert('is_array($this->RoleDescriptor)');
        assert('is_null($this->AffiliationDescriptor) || $this->AffiliationDescriptor instanceof SAML2_XML_md_AffiliationDescriptor');
        assert('is_null($this->Organization) || $this->Organization instanceof SAML2_XML_md_Organization');
        assert('is_array($this->ContactPerson)');
        assert('is_array($this->AdditionalMetadataLocation)');

        if ($parent === NULL) {
            $doc = SAML2_DOMDocumentFactory::create();
            $e = $doc->createElementNS(SAML2_Const::NS_MD, 'md:EntityDescriptor');
            $doc->appendChild($e);
        } else {
            $e = $parent->ownerDocument->createElementNS(SAML2_Const::NS_MD, 'md:EntityDescriptor');
            $parent->appendChild($e);
        }

        $e->setAttribute('entityID', $this->entityID);

        if (isset($this->ID)) {
            $e->setAttribute('ID', $this->ID);
        }

        if (isset($this->validUntil)) {
            $e->setAttribute('validUntil', gmdate('Y-m-d\TH:i:s\Z', $this->validUntil));
        }

        if (isset($this->cacheDuration)) {
            $e->setAttribute('cacheDuration', $this->cacheDuration);
        }

        SAML2_XML_md_Extensions::addList($e, $this->Extensions);

        /** @var SAML2_XML_md_UnknownRoleDescriptor|SAML2_XML_md_IDPSSODescriptor|SAML2_XML_md_SPSSODescriptor|SAML2_XML_md_AuthnAuthorityDescriptor|SAML2_XML_md_AttributeAuthorityDescriptor|SAML2_XML_md_PDPDescriptor $n */
        foreach ($this->RoleDescriptor as $n) {
            $n->toXML($e);
        }

        if (isset($this->AffiliationDescriptor)) {
            $this->AffiliationDescriptor->toXML($e);
        }

        if (isset($this->Organization)) {
            $this->Organization->toXML($e);
        }

        foreach ($this->ContactPerson as $cp) {
            $cp->toXML($e);
        }

        foreach ($this->AdditionalMetadataLocation as $n) {
            $n->toXML($e);
        }

        $this->signElement($e, $e->firstChild);

        return $e;
    }

}
