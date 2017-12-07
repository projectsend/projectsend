<?php

/**
 * Class representing SAML 2 metadata AdditionalMetadataLocation element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_AdditionalMetadataLocation
{
    /**
     * The namespace of this metadata.
     *
     * @var string
     */
    public $namespace;

    /**
     * The URI where the metadata is located.
     *
     * @var string
     */
    public $location;

    /**
     * Initialize an AdditionalMetadataLocation element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('namespace')) {
            throw new Exception('Missing namespace attribute on AdditionalMetadataLocation element.');
        }
        $this->namespace = $xml->getAttribute('namespace');

        $this->location = $xml->textContent;
    }

    /**
     * Convert this AdditionalMetadataLocation to XML.
     *
     * @param  DOMElement $parent The element we should append to.
     * @return DOMElement This AdditionalMetadataLocation-element.
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->namespace)');
        assert('is_string($this->location)');

        $e = SAML2_Utils::addString($parent, SAML2_Const::NS_MD, 'md:AdditionalMetadataLocation', $this->location);
        $e->setAttribute('namespace', $this->namespace);

        return $e;
    }

}
