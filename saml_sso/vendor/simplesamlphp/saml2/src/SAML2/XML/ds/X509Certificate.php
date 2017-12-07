<?php

/**
 * Class representing a ds:X509Certificate element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_ds_X509Certificate
{
    /**
     * The base64-encoded certificate.
     *
     * @var string
     */
    public $certificate;

    /**
     * Initialize an X509Certificate element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        $this->certificate = $xml->textContent;
    }

    /**
     * Convert this X509Certificate element to XML.
     *
     * @param DOMElement $parent The element we should append this X509Certificate element to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->certificate)');

        return SAML2_Utils::addString($parent, XMLSecurityDSig::XMLDSIGNS, 'ds:X509Certificate', $this->certificate);
    }

}
