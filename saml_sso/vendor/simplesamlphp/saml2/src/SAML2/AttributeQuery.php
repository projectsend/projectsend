<?php

/**
 * Class for SAML 2 attribute query messages.
 *
 * An attribute query asks for a set of attributes. The following
 * rules apply:
 *
 * - If no attributes are present in the query, all attributes should be
 *   returned.
 * - If any attributes are present, only those attributes which are present
 *   in the query should be returned.
 * - If an attribute contains any attribute values, only the attribute values
 *   which match those in the query should be returned.
 *
 * @package SimpleSAMLphp
 */
class SAML2_AttributeQuery extends SAML2_SubjectQuery
{
    /**
     * The attributes, as an associative array.
     *
     * @var array
     */
    private $attributes;

    /**
     * The NameFormat used on all attributes.
     *
     * If more than one NameFormat is used, this will contain
     * the unspecified nameformat.
     *
     * @var string
     */
    private $nameFormat;

    /**
     * Constructor for SAML 2 attribute query messages.
     *
     * @param DOMElement|NULL $xml The input message.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('AttributeQuery', $xml);

        $this->attributes = array();
        $this->nameFormat = SAML2_Const::NAMEFORMAT_UNSPECIFIED;

        if ($xml === NULL) {
            return;
        }

        $firstAttribute = TRUE;
        $attributes = SAML2_Utils::xpQuery($xml, './saml_assertion:Attribute');
        foreach ($attributes as $attribute) {
            if (!$attribute->hasAttribute('Name')) {
                throw new Exception('Missing name on <saml:Attribute> element.');
            }
            $name = $attribute->getAttribute('Name');

            if ($attribute->hasAttribute('NameFormat')) {
                $nameFormat = $attribute->getAttribute('NameFormat');
            } else {
                $nameFormat = SAML2_Const::NAMEFORMAT_UNSPECIFIED;
            }

            if ($firstAttribute) {
                $this->nameFormat = $nameFormat;
                $firstAttribute = FALSE;
            } else {
                if ($this->nameFormat !== $nameFormat) {
                    $this->nameFormat = SAML2_Const::NAMEFORMAT_UNSPECIFIED;
                }
            }

            if (!array_key_exists($name, $this->attributes)) {
                $this->attributes[$name] = array();
            }

            $values = SAML2_Utils::xpQuery($attribute, './saml_assertion:AttributeValue');
            foreach ($values as $value) {
                $this->attributes[$name][] = trim($value->textContent);
            }
        }
    }

    /**
     * Retrieve all requested attributes.
     *
     * @return array All requested attributes, as an associative array.
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set all requested attributes.
     *
     * @param array $attributes All requested attributes, as an associative array.
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Retrieve the NameFormat used on all attributes.
     *
     * If more than one NameFormat is used in the received attributes, this
     * returns the unspecified NameFormat.
     *
     * @return string The NameFormat used on all attributes.
     */
    public function getAttributeNameFormat()
    {
        return $this->nameFormat;
    }

    /**
     * Set the NameFormat used on all attributes.
     *
     * @param string $nameFormat The NameFormat used on all attributes.
     */
    public function setAttributeNameFormat($nameFormat)
    {
        assert('is_string($nameFormat)');

        $this->nameFormat = $nameFormat;
    }

    /**
     * Convert the attribute query message to an XML element.
     *
     * @return DOMElement This attribute query.
     */
    public function toUnsignedXML()
    {
        $root = parent::toUnsignedXML();

        foreach ($this->attributes as $name => $values) {
            $attribute = $root->ownerDocument->createElementNS(SAML2_Const::NS_SAML, 'saml:Attribute');
            $root->appendChild($attribute);
            $attribute->setAttribute('Name', $name);

            if ($this->nameFormat !== SAML2_Const::NAMEFORMAT_UNSPECIFIED) {
                $attribute->setAttribute('NameFormat', $this->nameFormat);
            }

            foreach ($values as $value) {
                if (is_string($value)) {
                    $type = 'xs:string';
                } elseif (is_int($value)) {
                    $type = 'xs:integer';
                } else {
                    $type = NULL;
                }

                $attributeValue = SAML2_Utils::addString($attribute, SAML2_Const::NS_SAML, 'saml:AttributeValue', $value);
                if ($type !== NULL) {
                    $attributeValue->setAttributeNS(SAML2_Const::NS_XSI, 'xsi:type', $type);
                }
            }
        }

        return $root;
    }

}
