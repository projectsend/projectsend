<?php

/**
 * Class representing a ds:KeyInfo element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_ds_KeyInfo
{
    /**
     * The Id attribute on this element.
     *
     * @var string|NULL
     */
    public $Id = NULL;

    /**
     * The various key information elements.
     *
     * Array with various elements describing this key.
     * Unknown elements will be represented by SAML2_XML_Chunk.
     *
     * @var (SAML2_XML_Chunk|SAML2_XML_ds_KeyName|SAML2_XML_ds_X509Data)[]
     */
    public $info = array();

    /**
     * Initialize a KeyInfo element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if ($xml->hasAttribute('Id')) {
            $this->Id = $xml->getAttribute('Id');
        }

        for ($n = $xml->firstChild; $n !== NULL; $n = $n->nextSibling) {
            if (!($n instanceof DOMElement)) {
                continue;
            }

            if ($n->namespaceURI !== XMLSecurityDSig::XMLDSIGNS) {
                $this->info[] = new SAML2_XML_Chunk($n);
                continue;
            }
            switch ($n->localName) {
                case 'KeyName':
                    $this->info[] = new SAML2_XML_ds_KeyName($n);
                    break;
                case 'X509Data':
                    $this->info[] = new SAML2_XML_ds_X509Data($n);
                    break;
                default:
                    $this->info[] = new SAML2_XML_Chunk($n);
                    break;
            }
        }
    }

    /**
     * Convert this KeyInfo to XML.
     *
     * @param DOMElement $parent The element we should append this KeyInfo to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_null($this->Id) || is_string($this->Id)');
        assert('is_array($this->info)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:KeyInfo');
        $parent->appendChild($e);

        if (isset($this->Id)) {
            $e->setAttribute('Id', $this->Id);
        }

        /** @var SAML2_XML_Chunk|SAML2_XML_ds_KeyName|SAML2_XML_ds_X509Data $n */
        foreach ($this->info as $n) {
            $n->toXML($e);
        }

        return $e;
    }

}
