<?php

/**
 * Class representing SAML 2 EndpointType.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_EndpointType
{
    /**
     * The binding for this endpoint.
     *
     * @var string
     */
    public $Binding;

    /**
     * The URI to this endpoint.
     *
     * @var string
     */
    public $Location;

    /**
     * The URI where responses can be delivered.
     *
     * @var string|NULL
     */
    public $ResponseLocation = NULL;

    /**
     * Extra (namespace qualified) attributes.
     *
     * @var array
     */
    private $attributes = array();

    /**
     * Initialize an EndpointType.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('Binding')) {
            throw new Exception('Missing Binding on ' . $xml->tagName);
        }
        $this->Binding = $xml->getAttribute('Binding');

        if (!$xml->hasAttribute('Location')) {
            throw new Exception('Missing Location on ' . $xml->tagName);
        }
        $this->Location = $xml->getAttribute('Location');

        if ($xml->hasAttribute('ResponseLocation')) {
            $this->ResponseLocation = $xml->getAttribute('ResponseLocation');
        }

        foreach ($xml->attributes as $a) {
            if ($a->namespaceURI === NULL) {
                continue; /* Not namespace-qualified -- skip. */
            }
            $fullName = '{' . $a->namespaceURI . '}' . $a->localName;
            $this->attributes[$fullName] = array(
                'qualifiedName' => $a->nodeName,
                'namespaceURI' => $a->namespaceURI,
                'value' => $a->value,
            );
        }
    }

    /**
     * Check if a namespace-qualified attribute exists.
     *
     * @param  string  $namespaceURI The namespace URI.
     * @param  string  $localName    The local name.
     * @return boolean TRUE if the attribute exists, FALSE if not.
     */
    public function hasAttributeNS($namespaceURI, $localName)
    {
        assert('is_string($namespaceURI)');
        assert('is_string($localName)');

        $fullName = '{' . $namespaceURI . '}' . $localName;

        return isset($this->attributes[$fullName]);
    }

    /**
     * Get a namespace-qualified attribute.
     *
     * @param  string $namespaceURI The namespace URI.
     * @param  string $localName    The local name.
     * @return string The value of the attribute, or an empty string if the attribute does not exist.
     */
    public function getAttributeNS($namespaceURI, $localName)
    {
        assert('is_string($namespaceURI)');
        assert('is_string($localName)');

        $fullName = '{' . $namespaceURI . '}' . $localName;
        if (!isset($this->attributes[$fullName])) {
            return '';
        }

        return $this->attributes[$fullName]['value'];
    }

    /**
     * Get a namespace-qualified attribute.
     *
     * @param string $namespaceURI  The namespace URI.
     * @param string $qualifiedName The local name.
     * @param string $value         The attribute value.
     * @throws Exception
     */
    public function setAttributeNS($namespaceURI, $qualifiedName, $value)
    {
        assert('is_string($namespaceURI)');
        assert('is_string($qualifiedName)');

        $name = explode(':', $qualifiedName, 2);
        if (count($name) < 2) {
            throw new Exception('Not a qualified name.');
        }
        $localName = $name[1];

        $fullName = '{' . $namespaceURI . '}' . $localName;
        $this->attributes[$fullName] = array(
            'qualifiedName' => $qualifiedName,
            'namespaceURI' => $namespaceURI,
            'value' => $value,
        );
    }

    /**
     * Remove a namespace-qualified attribute.
     *
     * @param string $namespaceURI The namespace URI.
     * @param string $localName    The local name.
     */
    public function removeAttributeNS($namespaceURI, $localName)
    {
        assert('is_string($namespaceURI)');
        assert('is_string($localName)');

        $fullName = '{' . $namespaceURI . '}' . $localName;
        unset($this->attributes[$fullName]);
    }

    /**
     * Add this endpoint to an XML element.
     *
     * @param DOMElement $parent The element we should append this endpoint to.
     * @param string     $name   The name of the element we should create.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent, $name)
    {
        assert('is_string($name)');
        assert('is_string($this->Binding)');
        assert('is_string($this->Location)');
        assert('is_null($this->ResponseLocation) || is_string($this->ResponseLocation)');

        $e = $parent->ownerDocument->createElementNS(SAML2_Const::NS_MD, $name);
        $parent->appendChild($e);

        $e->setAttribute('Binding', $this->Binding);
        $e->setAttribute('Location', $this->Location);

        if (isset($this->ResponseLocation)) {
            $e->setAttribute('ResponseLocation', $this->ResponseLocation);
        }

        foreach ($this->attributes as $a) {
            $e->setAttributeNS($a['namespaceURI'], $a['qualifiedName'], $a['value']);
        }

        return $e;
    }

}
