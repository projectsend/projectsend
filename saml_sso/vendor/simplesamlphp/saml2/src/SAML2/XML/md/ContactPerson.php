<?php

/**
 * Class representing SAML 2 ContactPerson.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_ContactPerson
{
    /**
     * The contact type.
     *
     * @var string
     */
    public $contactType;

    /**
     * Extensions on this element.
     *
     * Array of extension elements.
     *
     * @var array
     */
    public $Extensions = array();

    /**
     * The Company of this contact.
     *
     * @var string
     */
    public $Company = NULL;

    /**
     * The GivenName of this contact.
     *
     * @var string
     */
    public $GivenName = NULL;

    /**
     * The SurName of this contact.
     *
     * @var string
     */
    public $SurName = NULL;

    /**
     * The EmailAddresses of this contact.
     *
     * @var array
     */
    public $EmailAddress = array();

    /**
     * The TelephoneNumbers of this contact.
     *
     * @var array
     */
    public $TelephoneNumber = array();

    /**
     * Initialize a ContactPerson element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('contactType')) {
            throw new Exception('Missing contactType on ContactPerson.');
        }
        $this->contactType = $xml->getAttribute('contactType');

        $this->Extensions = SAML2_XML_md_Extensions::getList($xml);

        $this->Company = self::getStringElement($xml, 'Company');
        $this->GivenName = self::getStringElement($xml, 'GivenName');
        $this->SurName = self::getStringElement($xml, 'SurName');
        $this->EmailAddress = self::getStringElements($xml, 'EmailAddress');
        $this->TelephoneNumber = self::getStringElements($xml, 'TelephoneNumber');
    }

    /**
     * Retrieve the value of a child DOMElements as an array of strings.
     *
     * @param  DOMElement $parent The parent element.
     * @param  string     $name   The name of the child elements.
     * @return array      The value of the child elements.
     */
    private static function getStringElements(DOMElement $parent, $name)
    {
        assert('is_string($name)');

        $e = SAML2_Utils::xpQuery($parent, './saml_metadata:' . $name);

        $ret = array();
        foreach ($e as $i) {
            $ret[] = $i->textContent;
        }

        return $ret;
    }

    /**
     * Retrieve the value of a child DOMElement as a string.
     *
     * @param  DOMElement  $parent The parent element.
     * @param  string      $name   The name of the child element.
     * @return string|NULL The value of the child element.
     * @throws Exception
     */
    private static function getStringElement(DOMElement $parent, $name)
    {
        assert('is_string($name)');

        $e = self::getStringElements($parent, $name);
        if (empty($e)) {
            return NULL;
        }
        if (count($e) > 1) {
            throw new Exception('More than one ' . $name . ' in ' . $parent->tagName);
        }

        return $e[0];
    }

    /**
     * Convert this ContactPerson to XML.
     *
     * @param  DOMElement $parent The element we should add this contact to.
     * @return DOMElement The new ContactPerson-element.
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->contactType)');
        assert('is_array($this->Extensions)');
        assert('is_null($this->Company) || is_string($this->Company)');
        assert('is_null($this->GivenName) || is_string($this->GivenName)');
        assert('is_null($this->SurName) || is_string($this->SurName)');
        assert('is_array($this->EmailAddress)');
        assert('is_array($this->TelephoneNumber)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_Const::NS_MD, 'md:ContactPerson');
        $parent->appendChild($e);

        $e->setAttribute('contactType', $this->contactType);

        SAML2_XML_md_Extensions::addList($e, $this->Extensions);

        if (isset($this->Company)) {
            SAML2_Utils::addString($e, SAML2_Const::NS_MD, 'md:Company', $this->Company);
        }
        if (isset($this->GivenName)) {
            SAML2_Utils::addString($e, SAML2_Const::NS_MD, 'md:GivenName', $this->GivenName);
        }
        if (isset($this->SurName)) {
            SAML2_Utils::addString($e, SAML2_Const::NS_MD, 'md:SurName', $this->SurName);
        }
        if (!empty($this->EmailAddress)) {
            SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:EmailAddress', FALSE, $this->EmailAddress);
        }
        if (!empty($this->TelephoneNumber)) {
            SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:TelephoneNumber', FALSE, $this->TelephoneNumber);
        }

        return $e;
    }

}
