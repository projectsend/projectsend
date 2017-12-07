<?php

/**
 * Class representing SAML 2 Metadata AttributeConsumingService element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_AttributeConsumingService
{
    /**
     * The index of this AttributeConsumingService.
     *
     * @var int
     */
    public $index;

    /**
     * Whether this is the default AttributeConsumingService.
     *
     * @var bool|NULL
     */
    public $isDefault = NULL;

    /**
     * The ServiceName of this AttributeConsumingService.
     *
     * This is an associative array with language => translation.
     *
     * @var array
     */
    public $ServiceName = array();

    /**
     * The ServiceDescription of this AttributeConsumingService.
     *
     * This is an associative array with language => translation.
     *
     * @var array
     */
    public $ServiceDescription = array();

    /**
     * The RequestedAttribute elements.
     *
     * This is an array of SAML_RequestedAttributeType elements.
     *
     * @var SAML2_XML_md_RequestedAttribute[]
     */
    public $RequestedAttribute = array();

    /**
     * Initialize / parse an AttributeConsumingService.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('index')) {
            throw new Exception('Missing index on AttributeConsumingService.');
        }
        $this->index = (int) $xml->getAttribute('index');

        $this->isDefault = SAML2_Utils::parseBoolean($xml, 'isDefault', NULL);

        $this->ServiceName = SAML2_Utils::extractLocalizedStrings($xml, SAML2_Const::NS_MD, 'ServiceName');
        if (empty($this->ServiceName)) {
            throw new Exception('Missing ServiceName in AttributeConsumingService.');
        }

        $this->ServiceDescription = SAML2_Utils::extractLocalizedStrings($xml, SAML2_Const::NS_MD, 'ServiceDescription');

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:RequestedAttribute') as $ra) {
            $this->RequestedAttribute[] = new SAML2_XML_md_RequestedAttribute($ra);
        }
    }

    /**
     * Convert to DOMElement.
     *
     * @param DOMElement $parent The element we should append this AttributeConsumingService to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_int($this->index)');
        assert('is_null($this->isDefault) || is_bool($this->isDefault)');
        assert('is_array($this->ServiceName)');
        assert('is_array($this->ServiceDescription)');
        assert('is_array($this->RequestedAttribute)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_Const::NS_MD, 'md:AttributeConsumingService');
        $parent->appendChild($e);

        $e->setAttribute('index', (string) $this->index);

        if ($this->isDefault === TRUE) {
            $e->setAttribute('isDefault', 'true');
        } elseif ($this->isDefault === FALSE) {
            $e->setAttribute('isDefault', 'false');
        }

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:ServiceName', TRUE, $this->ServiceName);
        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:ServiceDescription', TRUE, $this->ServiceDescription);

        foreach ($this->RequestedAttribute as $ra) {
            $ra->toXML($e);
        }

        return $e;
    }

}
