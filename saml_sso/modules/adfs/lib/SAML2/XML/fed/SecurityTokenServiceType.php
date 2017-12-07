<?php
/**
 * Class representing SecurityTokenServiceType RoleDescriptor.
 *
 * @package SimpleSAMLphp
 */
class sspmod_adfs_SAML2_XML_fed_SecurityTokenServiceType extends SAML2_XML_md_RoleDescriptor {

	/**
	 * List of supported protocols.
	 *
	 * @var array
	 */
	public $protocolSupportEnumeration = array(sspmod_adfs_SAML2_XML_fed_Const::NS_FED);

	/**
	 * The Location of Services.
	 *
	 * @var string
	 */
	public $Location;

	/**
	 * Initialize a SecurityTokenServiceType element.
	 *
	 * @param DOMElement|NULL $xml  The XML element we should load.
	 */
	public function __construct(DOMElement $xml = NULL) {

                parent::__construct('RoleDescriptor', $xml);

		if ($xml === NULL) {
			return;
		}
	}

	/**
	 * Convert this SecurityTokenServiceType RoleDescriptor to XML.
	 *
	 * @param DOMElement $parent  The element we should add this contact to.
	 * @return DOMElement  The new ContactPerson-element.
	 */
	public function toXML(DOMElement $parent) {
		assert('is_string($this->Location)');

		$e = parent::toXML($parent);
		$e->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:fed', sspmod_adfs_SAML2_XML_fed_Const::NS_FED);
		$e->setAttributeNS(SAML2_Const::NS_XSI, 'xsi:type', 'fed:SecurityTokenServiceType');
                sspmod_adfs_SAML2_XML_fed_TokenTypesOffered::appendXML($e);
                sspmod_adfs_SAML2_XML_fed_Endpoint::appendXML($e, 'SecurityTokenServiceEndpoint', $this->Location);
                sspmod_adfs_SAML2_XML_fed_Endpoint::appendXML($e, 'fed:PassiveRequestorEndpoint', $this->Location);

		return $e;
	}
}
