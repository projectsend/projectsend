<?php

/**
 * Class representing SAML 2 metadata AttributeAuthorityDescriptor.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_AttributeAuthorityDescriptor extends SAML2_XML_md_RoleDescriptor
{
    /**
     * List of AttributeService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $AttributeService = array();

    /**
     * List of AssertionIDRequestService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $AssertionIDRequestService = array();

    /**
     * List of supported NameID formats.
     *
     * Array of strings.
     *
     * @var string[]
     */
    public $NameIDFormat = array();

    /**
     * List of supported attribute profiles.
     *
     * Array with strings.
     *
     * @var array
     */
    public $AttributeProfile = array();

    /**
     * List of supported attributes.
     *
     * Array with SAML2_XML_saml_Attribute objects.
     *
     * @var SAML2_XML_saml_Attribute[]
     */
    public $Attribute = array();

    /**
     * Initialize an IDPSSODescriptor.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('md:AttributeAuthorityDescriptor', $xml);

        if ($xml === NULL) {
            return;
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AttributeService') as $ep) {
            $this->AttributeService[] = new SAML2_XML_md_EndpointType($ep);
        }
        if (empty($this->AttributeService)) {
            throw new Exception('Must have at least one AttributeService in AttributeAuthorityDescriptor.');
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AssertionIDRequestService') as $ep) {
            $this->AssertionIDRequestService[] = new SAML2_XML_md_EndpointType($ep);
        }

        $this->NameIDFormat = SAML2_Utils::extractStrings($xml, SAML2_Const::NS_MD, 'NameIDFormat');

        $this->AttributeProfile = SAML2_Utils::extractStrings($xml, SAML2_Const::NS_MD, 'AttributeProfile');

        foreach (SAML2_Utils::xpQuery($xml, './saml_assertion:Attribute') as $a) {
            $this->Attribute[] = new SAML2_XML_saml_Attribute($a);
        }
    }

    /**
     * Add this AttributeAuthorityDescriptor to an EntityDescriptor.
     *
     * @param DOMElement $parent The EntityDescriptor we should append this IDPSSODescriptor to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_array($this->AttributeService)');
        assert('!empty($this->AttributeService)');
        assert('is_array($this->AssertionIDRequestService)');
        assert('is_array($this->NameIDFormat)');
        assert('is_array($this->AttributeProfile)');
        assert('is_array($this->Attribute)');

        $e = parent::toXML($parent);

        foreach ($this->AttributeService as $ep) {
            $ep->toXML($e, 'md:AttributeService');
        }

        foreach ($this->AssertionIDRequestService as $ep) {
            $ep->toXML($e, 'md:AssertionIDRequestService');
        }

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:NameIDFormat', FALSE, $this->NameIDFormat);

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:AttributeProfile', FALSE, $this->AttributeProfile);

        foreach ($this->Attribute as $a) {
            $a->toXML($e);
        }

        return $e;
    }

}
