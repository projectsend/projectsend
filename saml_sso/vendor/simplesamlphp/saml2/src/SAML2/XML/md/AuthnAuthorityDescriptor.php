<?php

/**
 * Class representing SAML 2 metadata AuthnAuthorityDescriptor.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_AuthnAuthorityDescriptor extends SAML2_XML_md_RoleDescriptor
{
    /**
     * List of AuthnQueryService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $AuthnQueryService = array();

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
     * Initialize an IDPSSODescriptor.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('md:AuthnAuthorityDescriptor', $xml);

        if ($xml === NULL) {
            return;
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AuthnQueryService') as $ep) {
            $this->AuthnQueryService[] = new SAML2_XML_md_EndpointType($ep);
        }
        if (empty($this->AuthnQueryService)) {
            throw new Exception('Must have at least one AuthnQueryService in AuthnAuthorityDescriptor.');
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AssertionIDRequestService') as $ep) {
            $this->AssertionIDRequestService[] = new SAML2_XML_md_EndpointType($ep);
        }

        $this->NameIDFormat = SAML2_Utils::extractStrings($xml, SAML2_Const::NS_MD, 'NameIDFormat');
    }

    /**
     * Add this IDPSSODescriptor to an EntityDescriptor.
     *
     * @param DOMElement $parent The EntityDescriptor we should append this AuthnAuthorityDescriptor to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_array($this->AuthnQueryService)');
        assert('!empty($this->AuthnQueryService)');
        assert('is_array($this->AssertionIDRequestService)');
        assert('is_array($this->NameIDFormat)');

        $e = parent::toXML($parent);

        foreach ($this->AuthnQueryService as $ep) {
            $ep->toXML($e, 'md:AuthnQueryService');
        }

        foreach ($this->AssertionIDRequestService as $ep) {
            $ep->toXML($e, 'md:AssertionIDRequestService');
        }

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:NameIDFormat', FALSE, $this->NameIDFormat);

        return $e;
    }

}
