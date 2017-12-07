<?php

/**
 * Class representing SAML 2 RoleDescriptor element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_RoleDescriptor extends SAML2_SignedElementHelper
{
    /**
     * The name of this descriptor element.
     *
     * @var string
     */
    private $elementName;

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
     * List of supported protocols.
     *
     * @var array
     */
    public $protocolSupportEnumeration = array();

    /**
     * Error URL for this role.
     *
     * @var string|NULL
     */
    public $errorURL;

    /**
     * Extensions on this element.
     *
     * Array of extension elements.
     *
     * @var array
     */
    public $Extensions = array();

    /**
     * KeyDescriptor elements.
     *
     * Array of SAML2_XML_md_KeyDescriptor elements.
     *
     * @var SAML2_XML_md_KeyDescriptor[]
     */
    public $KeyDescriptor = array();

    /**
     * Organization of this role.
     *
     * @var SAML2_XML_md_Organization|NULL
     */
    public $Organization = NULL;

    /**
     * ContactPerson elements for this role.
     *
     * Array of SAML2_XML_md_ContactPerson objects.
     *
     * @var SAML2_XML_md_ContactPerson[]
     */
    public $ContactPerson = array();

    /**
     * Initialize a RoleDescriptor.
     *
     * @param string          $elementName The name of this element.
     * @param DOMElement|NULL $xml         The XML element we should load.
     * @throws Exception
     */
    protected function __construct($elementName, DOMElement $xml = NULL)
    {
        assert('is_string($elementName)');

        parent::__construct($xml);
        $this->elementName = $elementName;

        if ($xml === NULL) {
            return;
        }

        if ($xml->hasAttribute('ID')) {
            $this->ID = $xml->getAttribute('ID');
        }
        if ($xml->hasAttribute('validUntil')) {
            $this->validUntil = SAML2_Utils::xsDateTimeToTimestamp($xml->getAttribute('validUntil'));
        }
        if ($xml->hasAttribute('cacheDuration')) {
            $this->cacheDuration = $xml->getAttribute('cacheDuration');
        }

        if (!$xml->hasAttribute('protocolSupportEnumeration')) {
            throw new Exception('Missing protocolSupportEnumeration attribute on ' . $xml->localName);
        }
        $this->protocolSupportEnumeration = preg_split('/[\s]+/', $xml->getAttribute('protocolSupportEnumeration'));

        if ($xml->hasAttribute('errorURL')) {
            $this->errorURL = $xml->getAttribute('errorURL');
        }

        $this->Extensions = SAML2_XML_md_Extensions::getList($xml);

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:KeyDescriptor') as $kd) {
            $this->KeyDescriptor[] = new SAML2_XML_md_KeyDescriptor($kd);
        }

        $organization = SAML2_Utils::xpQuery($xml, './saml_metadata:Organization');
        if (count($organization) > 1) {
            throw new Exception('More than one Organization in the entity.');
        } elseif (!empty($organization)) {
            $this->Organization = new SAML2_XML_md_Organization($organization[0]);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:ContactPerson') as $cp) {
            $this->contactPersons[] = new SAML2_XML_md_ContactPerson($cp);
        }
    }

    /**
     * Add this RoleDescriptor to an EntityDescriptor.
     *
     * @param DOMElement $parent The EntityDescriptor we should append this endpoint to.
     * @return DOMElement
     */
    protected function toXML(DOMElement $parent)
    {
        assert('is_null($this->ID) || is_string($this->ID)');
        assert('is_null($this->validUntil) || is_int($this->validUntil)');
        assert('is_null($this->cacheDuration) || is_string($this->cacheDuration)');
        assert('is_array($this->protocolSupportEnumeration)');
        assert('is_null($this->errorURL) || is_string($this->errorURL)');
        assert('is_array($this->Extensions)');
        assert('is_array($this->KeyDescriptor)');
        assert('is_null($this->Organization) || $this->Organization instanceof SAML2_XML_md_Organization');
        assert('is_array($this->ContactPerson)');

        $e = $parent->ownerDocument->createElementNS(SAML2_Const::NS_MD, $this->elementName);
        $parent->appendChild($e);

        if (isset($this->ID)) {
            $e->setAttribute('ID', $this->ID);
        }

        if (isset($this->validUntil)) {
            $e->setAttribute('validUntil', gmdate('Y-m-d\TH:i:s\Z', $this->validUntil));
        }

        if (isset($this->cacheDuration)) {
            $e->setAttribute('cacheDuration', $this->cacheDuration);
        }

        $e->setAttribute('protocolSupportEnumeration', implode(' ', $this->protocolSupportEnumeration));

        if (isset($this->errorURL)) {
            $e->setAttribute('errorURL', $this->errorURL);
        }

        SAML2_XML_md_Extensions::addList($e, $this->Extensions);

        foreach ($this->KeyDescriptor as $kd) {
            $kd->toXML($e);
        }

        if (isset($this->Organization)) {
            $this->Organization->toXML($e);
        }

        foreach ($this->ContactPerson as $cp) {
            $cp->toXML($e);
        }

        return $e;
    }

}
