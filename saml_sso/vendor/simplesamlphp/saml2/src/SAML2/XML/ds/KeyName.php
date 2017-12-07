<?php

/**
 * Class representing a ds:KeyName element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_ds_KeyName
{
    /**
     * The key name.
     *
     * @var string
     */
    public $name;

    /**
     * Initialize a KeyName element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        $this->name = $xml->textContent;
    }

    /**
     * Convert this KeyName element to XML.
     *
     * @param DOMElement $parent The element we should append this KeyName element to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->name)');

        return SAML2_Utils::addString($parent, XMLSecurityDSig::XMLDSIGNS, 'ds:KeyName', $this->name);
    }

}
