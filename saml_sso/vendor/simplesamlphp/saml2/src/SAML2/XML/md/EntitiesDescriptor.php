<?php

/**
 * Class representing SAML 2 EntitiesDescriptor element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_EntitiesDescriptor extends SAML2_SignedElementHelper
{
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
     * The name of this entity collection.
     *
     * @var string|NULL
     */
    public $Name;

    /**
     * Extensions on this element.
     *
     * Array of extension elements.
     *
     * @var array
     */
    public $Extensions = array();

    /**
     * Child EntityDescriptor and EntitiesDescriptor elements.
     *
     * @var (SAML2_XML_md_EntityDescriptor|SAML2_XML_md_EntitiesDescriptor)[]
     */
    public $children = array();

    /**
     * Initialize an EntitiesDescriptor.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct($xml);

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
        if ($xml->hasAttribute('Name')) {
            $this->Name = $xml->getAttribute('Name');
        }

        $this->Extensions = SAML2_XML_md_Extensions::getList($xml);

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:EntityDescriptor|./saml_metadata:EntitiesDescriptor') as $node) {
            if ($node->localName === 'EntityDescriptor') {
                $this->children[] = new SAML2_XML_md_EntityDescriptor($node);
            } else {
                $this->children[] = new SAML2_XML_md_EntitiesDescriptor($node);
            }
        }
    }

    /**
     * Convert this EntitiesDescriptor to XML.
     *
     * @param DOMElement|NULL $parent The EntitiesDescriptor we should append this EntitiesDescriptor to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent = NULL)
    {
        assert('is_null($this->ID) || is_string($this->ID)');
        assert('is_null($this->validUntil) || is_int($this->validUntil)');
        assert('is_null($this->cacheDuration) || is_string($this->cacheDuration)');
        assert('is_null($this->Name) || is_string($this->Name)');
        assert('is_array($this->Extensions)');
        assert('is_array($this->children)');

        if ($parent === NULL) {
            $doc = SAML2_DOMDocumentFactory::create();
            $e = $doc->createElementNS(SAML2_Const::NS_MD, 'md:EntitiesDescriptor');
            $doc->appendChild($e);
        } else {
            $e = $parent->ownerDocument->createElementNS(SAML2_Const::NS_MD, 'md:EntitiesDescriptor');
            $parent->appendChild($e);
        }

        if (isset($this->ID)) {
            $e->setAttribute('ID', $this->ID);
        }

        if (isset($this->validUntil)) {
            $e->setAttribute('validUntil', gmdate('Y-m-d\TH:i:s\Z', $this->validUntil));
        }

        if (isset($this->cacheDuration)) {
            $e->setAttribute('cacheDuration', $this->cacheDuration);
        }

        if (isset($this->Name)) {
            $e->setAttribute('Name', $this->Name);
        }

        SAML2_XML_md_Extensions::addList($e, $this->Extensions);

        /** @var SAML2_XML_md_EntityDescriptor|SAML2_XML_md_EntitiesDescriptor $node */
        foreach ($this->children as $node) {
            $node->toXML($e);
        }

        $this->signElement($e, $e->firstChild);

        return $e;
    }

}
