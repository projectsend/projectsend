<?php

/**
 * Class representing SAML 2 SSODescriptorType.
 *
 * @package SimpleSAMLphp
 */
abstract class SAML2_XML_md_SSODescriptorType extends SAML2_XML_md_RoleDescriptor
{
    /**
     * List of ArtifactResolutionService endpoints.
     *
     * Array with IndexedEndpointType objects.
     *
     * @var SAML2_XML_md_IndexedEndpointType[]
     */
    public $ArtifactResolutionService = array();

    /**
     * List of SingleLogoutService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $SingleLogoutService = array();

    /**
     * List of ManageNameIDService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $ManageNameIDService = array();

    /**
     * List of supported NameID formats.
     *
     * Array of strings.
     *
     * @var string[]
     */
    public $NameIDFormat = array();

    /**
     * Initialize a SSODescriptor.
     *
     * @param string          $elementName The name of this element.
     * @param DOMElement|NULL $xml         The XML element we should load.
     */
    protected function __construct($elementName, DOMElement $xml = NULL)
    {
        assert('is_string($elementName)');

        parent::__construct($elementName, $xml);

        if ($xml === NULL) {
            return;
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:ArtifactResolutionService') as $ep) {
            $this->ArtifactResolutionService[] = new SAML2_XML_md_IndexedEndpointType($ep);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:SingleLogoutService') as $ep) {
            $this->SingleLogoutService[] = new SAML2_XML_md_EndpointType($ep);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:ManageNameIDService') as $ep) {
            $this->ManageNameIDService[] = new SAML2_XML_md_EndpointType($ep);
        }

        $this->NameIDFormat = SAML2_Utils::extractStrings($xml, SAML2_Const::NS_MD, 'NameIDFormat');
    }

    /**
     * Add this SSODescriptorType to an EntityDescriptor.
     *
     * @param  DOMElement $parent The EntityDescriptor we should append this SSODescriptorType to.
     * @return DOMElement The generated SSODescriptor DOMElement.
     */
    protected function toXML(DOMElement $parent)
    {
        assert('is_array($this->ArtifactResolutionService)');
        assert('is_array($this->SingleLogoutService)');
        assert('is_array($this->ManageNameIDService)');
        assert('is_array($this->NameIDFormat)');

        $e = parent::toXML($parent);

        foreach ($this->ArtifactResolutionService as $ep) {
            $ep->toXML($e, 'md:ArtifactResolutionService');
        }

        foreach ($this->SingleLogoutService as $ep) {
            $ep->toXML($e, 'md:SingleLogoutService');
        }

        foreach ($this->ManageNameIDService as $ep) {
            $ep->toXML($e, 'md:ManageNameIDService');
        }

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:NameIDFormat', FALSE, $this->NameIDFormat);

        return $e;
    }

}
