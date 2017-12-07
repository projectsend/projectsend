<?php

/**
 * Class representing SAML 2 AffiliationDescriptor element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_AffiliationDescriptor extends SAML2_SignedElementHelper
{
    /**
     * The affiliationOwnerID.
     *
     * @var string
     */
    public $affiliationOwnerID;

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
     * The AffiliateMember(s).
     *
     * Array of entity ID strings.
     *
     * @var array
     */
    public $AffiliateMember = array();

    /**
     * KeyDescriptor elements.
     *
     * Array of SAML2_XML_md_KeyDescriptor elements.
     *
     * @var SAML2_XML_md_KeyDescriptor[]
     */
    public $KeyDescriptor = array();

    /**
     * Initialize a AffiliationDescriptor.
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

        if (!$xml->hasAttribute('affiliationOwnerID')) {
            throw new Exception('Missing affiliationOwnerID on AffiliationDescriptor.');
        }
        $this->affiliationOwnerID = $xml->getAttribute('affiliationOwnerID');

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

        $this->AffiliateMember = SAML2_Utils::extractStrings($xml, SAML2_Const::NS_MD, 'AffiliateMember');
        if (empty($this->AffiliateMember)) {
            throw new Exception('Missing AffiliateMember in AffiliationDescriptor.');
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:KeyDescriptor') as $kd) {
            $this->KeyDescriptor[] = new SAML2_XML_md_KeyDescriptor($kd);
        }
    }

    /**
     * Add this AffiliationDescriptor to an EntityDescriptor.
     *
     * @param DOMElement $parent The EntityDescriptor we should append this endpoint to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->affiliationOwnerID)');
        assert('is_null($this->ID) || is_string($this->ID)');
        assert('is_null($this->validUntil) || is_int($this->validUntil)');
        assert('is_null($this->cacheDuration) || is_string($this->cacheDuration)');
        assert('is_array($this->Extensions)');
        assert('is_array($this->AffiliateMember)');
        assert('!empty($this->AffiliateMember)');
        assert('is_array($this->KeyDescriptor)');

        $e = $parent->ownerDocument->createElementNS(SAML2_Const::NS_MD, 'md:AffiliationDescriptor');
        $parent->appendChild($e);

        $e->setAttribute('affiliationOwnerID', $this->affiliationOwnerID);

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

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:AffiliateMember', FALSE, $this->AffiliateMember);

        foreach ($this->KeyDescriptor as $kd) {
            $kd->toXML($e);
        }

        $this->signElement($e, $e->firstChild);

        return $e;
    }

}
