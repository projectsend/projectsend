<?php

/**
 * Class representing SAML 2 Organization element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_Organization
{
    /**
     * Extensions on this element.
     *
     * Array of extension elements.
     *
     * @var array
     */
    public $Extensions = array();

    /**
     * The OrganizationName, as an array of language => translation.
     *
     * @var array
     */
    public $OrganizationName = array();

    /**
     * The OrganizationDisplayName, as an array of language => translation.
     *
     * @var array
     */
    public $OrganizationDisplayName = array();

    /**
     * The OrganizationURL, as an array of language => translation.
     *
     * @var array
     */
    public $OrganizationURL = array();

    /**
     * Initialize an Organization element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        $this->Extensions = SAML2_XML_md_Extensions::getList($xml);

        $this->OrganizationName = SAML2_Utils::extractLocalizedStrings($xml, SAML2_Const::NS_MD, 'OrganizationName');
        if (empty($this->OrganizationName)) {
            $this->OrganizationName = array('invalid' => '');
        }

        $this->OrganizationDisplayName = SAML2_Utils::extractLocalizedStrings($xml, SAML2_Const::NS_MD, 'OrganizationDisplayName');
        if (empty($this->OrganizationDisplayName)) {
            $this->OrganizationDisplayName = array('invalid' => '');
        }

        $this->OrganizationURL = SAML2_Utils::extractLocalizedStrings($xml, SAML2_Const::NS_MD, 'OrganizationURL');
        if (empty($this->OrganizationURL)) {
            $this->OrganizationURL = array('invalid' => '');
        }
    }

    /**
     * Convert this Organization to XML.
     *
     * @param  DOMElement $parent The element we should add this organization to.
     * @return DOMElement This Organization-element.
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_array($this->Extensions)');
        assert('is_array($this->OrganizationName)');
        assert('!empty($this->OrganizationName)');
        assert('is_array($this->OrganizationDisplayName)');
        assert('!empty($this->OrganizationDisplayName)');
        assert('is_array($this->OrganizationURL)');
        assert('!empty($this->OrganizationURL)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_Const::NS_MD, 'md:Organization');
        $parent->appendChild($e);

        SAML2_XML_md_Extensions::addList($e, $this->Extensions);

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:OrganizationName', TRUE, $this->OrganizationName);
        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:OrganizationDisplayName', TRUE, $this->OrganizationDisplayName);
        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:OrganizationURL', TRUE, $this->OrganizationURL);

        return $e;
    }

}
