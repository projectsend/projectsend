<?php

/**
 * Class for handling the EntityAttributes metadata extension.
 *
 * @link: http://docs.oasis-open.org/security/saml/Post2.0/sstc-metadata-attr-cs-01.pdf
 * @package SimpleSAMLphp
 */
class SAML2_XML_mdattr_EntityAttributes
{
    /**
     * The namespace used for the EntityAttributes extension.
     */
    const NS = 'urn:oasis:names:tc:SAML:metadata:attribute';

    /**
     * Array with child elements.
     *
     * The elements can be SAML2_XML_saml_Attribute or SAML2_XML_Chunk elements.
     *
     * @var (SAML2_XML_saml_Attribute|SAML2_XML_Chunk)[]
     */
    public $children;

    /**
     * Create a EntityAttributes element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_assertion:Attribute|./saml_assertion:Assertion') as $node) {
            if ($node->localName === 'Attribute') {
                $this->children[] = new SAML2_XML_saml_Attribute($node);
            } else {
                $this->children[] = new SAML2_XML_Chunk($node);
            }
        }

    }

    /**
     * Convert this EntityAttributes to XML.
     *
     * @param DOMElement $parent The element we should append to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_array($this->children)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_XML_mdattr_EntityAttributes::NS, 'mdattr:EntityAttributes');
        $parent->appendChild($e);

        /** @var SAML2_XML_saml_Attribute|SAML2_XML_Chunk $child */
        foreach ($this->children as $child) {
            $child->toXML($e);
        }

        return $e;
    }

}
