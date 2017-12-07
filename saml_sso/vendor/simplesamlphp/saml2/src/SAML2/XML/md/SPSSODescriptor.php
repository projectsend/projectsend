<?php

/**
 * Class representing SAML 2 SPSSODescriptor.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_SPSSODescriptor extends SAML2_XML_md_SSODescriptorType
{
    /**
     * Whether this SP signs authentication requests.
     *
     * @var bool|NULL
     */
    public $AuthnRequestsSigned = NULL;

    /**
     * Whether this SP wants the Assertion elements to be signed.
     *
     * @var bool|NULL
     */
    public $WantAssertionsSigned = NULL;

    /**
     * List of AssertionConsumerService endpoints for this SP.
     *
     * Array with IndexedEndpointType objects.
     *
     * @var SAML2_XML_md_IndexedEndpointType[]
     */
    public $AssertionConsumerService = array();

    /**
     * List of AttributeConsumingService descriptors for this SP.
     *
     * Array with SAML2_XML_md_AttributeConsumingService objects.
     *
     * @var SAML2_XML_md_AttributeConsumingService[]
     */
    public $AttributeConsumingService = array();

    /**
     * Initialize a SPSSODescriptor.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('md:SPSSODescriptor', $xml);

        if ($xml === NULL) {
            return;
        }

        $this->AuthnRequestsSigned = SAML2_Utils::parseBoolean($xml, 'AuthnRequestsSigned', NULL);
        $this->WantAssertionsSigned = SAML2_Utils::parseBoolean($xml, 'WantAssertionsSigned', NULL);

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AssertionConsumerService') as $ep) {
            $this->AssertionConsumerService[] = new SAML2_XML_md_IndexedEndpointType($ep);
        }

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:AttributeConsumingService') as $acs) {
            $this->AttributeConsumingService[] = new SAML2_XML_md_AttributeConsumingService($acs);
        }
    }

    /**
     * Add this SPSSODescriptor to an EntityDescriptor.
     *
     * @param DOMElement $parent The EntityDescriptor we should append this SPSSODescriptor to.
     * @return void
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_null($this->AuthnRequestsSigned) || is_bool($this->AuthnRequestsSigned)');
        assert('is_null($this->WantAssertionsSigned) || is_bool($this->WantAssertionsSigned)');
        assert('is_array($this->AssertionConsumerService)');
        assert('is_array($this->AttributeConsumingService)');

        $e = parent::toXML($parent);

        if ($this->AuthnRequestsSigned === TRUE) {
            $e->setAttribute('AuthnRequestsSigned', 'true');
        } elseif ($this->AuthnRequestsSigned === FALSE) {
            $e->setAttribute('AuthnRequestsSigned', 'false');
        }

        if ($this->WantAssertionsSigned === TRUE) {
            $e->setAttribute('WantAssertionsSigned', 'true');
        } elseif ($this->WantAssertionsSigned === FALSE) {
            $e->setAttribute('WantAssertionsSigned', 'false');
        }

        foreach ($this->AssertionConsumerService as $ep) {
            $ep->toXML($e, 'md:AssertionConsumerService');
        }

        foreach ($this->AttributeConsumingService as $acs) {
            $acs->toXML($e);
        }
    }

}
