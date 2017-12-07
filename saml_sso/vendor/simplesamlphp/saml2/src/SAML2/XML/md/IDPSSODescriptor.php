<?php

/**
 * Class representing SAML 2 IDPSSODescriptor.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_IDPSSODescriptor extends SAML2_XML_md_SSODescriptorType
{
    /**
     * Whether AuthnRequests sent to this IdP should be signed.
     *
     * @var bool|NULL
     */
    public $WantAuthnRequestsSigned = NULL;

    /**
     * List of SingleSignOnService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $SingleSignOnService = array();

    /**
     * List of NameIDMappingService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $NameIDMappingService = array();

    /**
     * List of AssertionIDRequestService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $AssertionIDRequestService = array();

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
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('md:IDPSSODescriptor', $xml);

        if ($xml === NULL) {
            return;
        }

        $this->WantAuthnRequestsSigned = SAML2_Utils::parseBoolean($xml, 'WantAuthnRequestsSigned', NULL);

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:SingleSignOnService') as $ep) {
            $this->SingleSignOnService[] = new SAML2_XML_md_EndpointType($ep);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:NameIDMappingService') as $ep) {
            $this->NameIDMappingService[] = new SAML2_XML_md_EndpointType($ep);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AssertionIDRequestService') as $ep) {
            $this->AssertionIDRequestService[] = new SAML2_XML_md_EndpointType($ep);
        }

        $this->AttributeProfile = SAML2_Utils::extractStrings($xml, SAML2_Const::NS_MD, 'AttributeProfile');

        foreach (SAML2_Utils::xpQuery($xml, './saml_assertion:Attribute') as $a) {
            $this->Attribute[] = new SAML2_XML_saml_Attribute($a);
        }
    }

    /**
     * Add this IDPSSODescriptor to an EntityDescriptor.
     *
     * @param DOMElement $parent The EntityDescriptor we should append this IDPSSODescriptor to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_null($this->WantAuthnRequestsSigned) || is_bool($this->WantAuthnRequestsSigned)');
        assert('is_array($this->SingleSignOnService)');
        assert('is_array($this->NameIDMappingService)');
        assert('is_array($this->AssertionIDRequestService)');
        assert('is_array($this->AttributeProfile)');
        assert('is_array($this->Attribute)');

        $e = parent::toXML($parent);

        if ($this->WantAuthnRequestsSigned === TRUE) {
            $e->setAttribute('WantAuthnRequestsSigned', 'true');
        } elseif ($this->WantAuthnRequestsSigned === FALSE) {
            $e->setAttribute('WantAuthnRequestsSigned', 'false');
        }

        foreach ($this->SingleSignOnService as $ep) {
            $ep->toXML($e, 'md:SingleSignOnService');
        }

        foreach ($this->NameIDMappingService as $ep) {
            $ep->toXML($e, 'md:NameIDMappingService');
        }

        foreach ($this->AssertionIDRequestService as $ep) {
            $ep->toXML($e, 'md:AssertionIDRequestService');
        }

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:AttributeProfile', FALSE, $this->AttributeProfile);

        foreach ($this->Attribute as $a) {
            $a->toXML($e);
        }

        return $e;
    }

}
