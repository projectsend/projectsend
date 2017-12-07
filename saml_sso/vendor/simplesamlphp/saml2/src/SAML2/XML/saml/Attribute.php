<?php

/**
 * Class representing SAML 2 Attribute.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_saml_Attribute
{
    /**
     * The Name of this attribute.
     *
     * @var string
     */
    public $Name;

    /**
     * The NameFormat of this attribute.
     *
     * @var string|NULL
     */
    public $NameFormat;

    /**
     * The FriendlyName of this attribute.
     *
     * @var string|NULL
     */
    public $FriendlyName = NULL;

    /**
     * List of attribute values.
     *
     * Array of SAML2_XML_saml_AttributeValue elements.
     *
     * @var SAML2_XML_saml_AttributeValue[]
     */
    public $AttributeValue = array();

    /**
     * Initialize an Attribute.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('Name')) {
            throw new Exception('Missing Name on Attribute.');
        }
        $this->Name = $xml->getAttribute('Name');

        if ($xml->hasAttribute('NameFormat')) {
            $this->NameFormat = $xml->getAttribute('NameFormat');
        }

        if ($xml->hasAttribute('FriendlyName')) {
            $this->FriendlyName = $xml->getAttribute('FriendlyName');
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_assertion:AttributeValue') as $av) {
            $this->AttributeValue[] = new SAML2_XML_saml_AttributeValue($av);
        }
    }

    /**
     * Internal implementation of toXML.
     * This function allows RequestedAttribute to specify the element name and namespace.
     *
     * @param DOMElement $parent    The element we should append this Attribute to.
     * @param string     $namespace The namespace the element should be created in.
     * @param string     $name      The name of the element.
     * @return DOMElement
     */
    protected function toXMLInternal(DOMElement $parent, $namespace, $name)
    {
        assert('is_string($namespace)');
        assert('is_string($name)');
        assert('is_string($this->Name)');
        assert('is_null($this->NameFormat) || is_string($this->NameFormat)');
        assert('is_null($this->FriendlyName) || is_string($this->FriendlyName)');
        assert('is_array($this->AttributeValue)');

        $e = $parent->ownerDocument->createElementNS($namespace, $name);
        $parent->appendChild($e);

        $e->setAttribute('Name', $this->Name);

        if (isset($this->NameFormat)) {
            $e->setAttribute('NameFormat', $this->NameFormat);
        }

        if (isset($this->FriendlyName)) {
            $e->setAttribute('FriendlyName', $this->FriendlyName);
        }

        foreach ($this->AttributeValue as $av) {
            $av->toXML($e);
        }

        return $e;
    }

    /**
     * Convert this Attribute to XML.
     *
     * @param DOMElement $parent The element we should append this Attribute to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        return $this->toXMLInternal($parent, SAML2_Const::NS_SAML, 'saml:Attribute');
    }

}
