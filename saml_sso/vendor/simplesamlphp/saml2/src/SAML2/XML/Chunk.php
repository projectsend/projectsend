<?php
/**
 * Serializable class used to hold an XML element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_Chunk implements Serializable
{
    /**
     * The localName of the element.
     *
     * @var string
     */
    public $localName;

    /**
     * The namespaceURI of this element.
     *
     * @var string
     */
    public $namespaceURI;

    /**
     * The DOMElement we contain.
     *
     * @var DOMElement
     */
    public $xml;

    /**
     * Create a XMLChunk from a copy of the given DOMElement.
     *
     * @param DOMElement $xml The element we should copy.
     */
    public function __construct(DOMElement $xml)
    {
        $this->localName = $xml->localName;
        $this->namespaceURI = $xml->namespaceURI;

        $this->xml = SAML2_Utils::copyElement($xml);
    }

    /**
     * Get this DOMElement.
     *
     * @return DOMElement This element.
     * @deprecated
     */
    public function getXML()
    {
        return $this->xml;
    }

    /**
     * Append this XML element to a different XML element.
     *
     * @param  DOMElement $parent The element we should append this element to.
     * @return DOMElement The new element.
     */
    public function toXML(DOMElement $parent)
    {
        return SAML2_Utils::copyElement($this->xml, $parent);
    }

    /**
     * Serialize this XML chunk.
     *
     * @return string The serialized chunk.
     */
    public function serialize()
    {
        return serialize($this->xml->ownerDocument->saveXML($this->xml));
    }

    /**
     * Un-serialize this XML chunk.
     *
     * @param  string          $serialized The serialized chunk.
     * @return SAML2_XML_Chunk The chunk object represented by the serialized string.
     */
    public function unserialize($serialized)
    {
        $doc = SAML2_DOMDocumentFactory::fromString(unserialize($serialized));
        $this->xml = $doc->documentElement;
        $this->localName = $this->xml->localName;
        $this->namespaceURI = $this->xml->namespaceURI;
    }
}
