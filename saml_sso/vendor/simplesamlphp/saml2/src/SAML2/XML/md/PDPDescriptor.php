<?php

/**
 * Class representing SAML 2 metadata PDPDescriptor.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_PDPDescriptor extends SAML2_XML_md_RoleDescriptor
{
    /**
     * List of AuthzService endpoints.
     *
     * Array with EndpointType objects.
     *
     * @var SAML2_XML_md_EndpointType[]
     */
    public $AuthzService = array();

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
        parent::__construct('md:PDPDescriptor', $xml);

        if ($xml === NULL) {
            return;
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AuthzService') as $ep) {
            $this->AuthzService[] = new SAML2_XML_md_EndpointType($ep);
        }
        if (empty($this->AuthzService)) {
            throw new Exception('Must have at least one AuthzService in PDPDescriptor.');
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AssertionIDRequestService') as $ep) {
            $this->AssertionIDRequestService[] = new SAML2_XML_md_EndpointType($ep);
        }

        $this->NameIDFormat = SAML2_Utils::extractStrings($xml, SAML2_Const::NS_MD, 'NameIDFormat');
    }

    /**
     * Add this PDPDescriptor to an EntityDescriptor.
     *
     * @param DOMElement $parent The EntityDescriptor we should append this IDPSSODescriptor to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_array($this->AuthzService)');
        assert('!empty($this->AuthzService)');
        assert('is_array($this->AssertionIDRequestService)');
        assert('is_array($this->NameIDFormat)');

        $e = parent::toXML($parent);

        foreach ($this->AuthzService as $ep) {
            $ep->toXML($e, 'md:AuthzService');
        }

        foreach ($this->AssertionIDRequestService as $ep) {
            $ep->toXML($e, 'md:AssertionIDRequestService');
        }

        SAML2_Utils::addStrings($e, SAML2_Const::NS_MD, 'md:NameIDFormat', FALSE, $this->NameIDFormat);

        return $e;
    }

}
