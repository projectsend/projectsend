<?php

/**
 * Class representing a ds:X509Data element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_ds_X509Data
{
    /**
     * The various X509 data elements.
     *
     * Array with various elements describing this certificate.
     * Unknown elements will be represented by SAML2_XML_Chunk.
     *
     * @var (SAML2_XML_Chunk|SAML2_XML_ds_X509Certificate)[]
     */
    public $data = array();

    /**
     * Initialize a X509Data.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        for ($n = $xml->firstChild; $n !== NULL; $n = $n->nextSibling) {
            if (!($n instanceof DOMElement)) {
                continue;
            }

            if ($n->namespaceURI !== XMLSecurityDSig::XMLDSIGNS) {
                $this->data[] = new SAML2_XML_Chunk($n);
                continue;
            }
            switch ($n->localName) {
                case 'X509Certificate':
                    $this->data[] = new SAML2_XML_ds_X509Certificate($n);
                    break;
                default:
                    $this->data[] = new SAML2_XML_Chunk($n);
                    break;
            }
        }
    }

    /**
     * Convert this X509Data element to XML.
     *
     * @param DOMElement $parent The element we should append this X509Data element to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_array($this->data)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509Data');
        $parent->appendChild($e);

        /** @var SAML2_XML_Chunk|SAML2_XML_ds_X509Certificate $n */
        foreach ($this->data as $n) {
            $n->toXML($e);
        }

        return $e;
    }

}
