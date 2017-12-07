<?php
/**
 * Class representing fed Endpoint.
 *
 * @package SimpleSAMLphp
 */
class sspmod_adfs_SAML2_XML_fed_Endpoint {
	/**
	 * Add this endpoint to an XML element.
	 *
	 * @param DOMElement $parent  The element we should append this endpoint to.
	 * @param string $name  The name of the element we should create.
	 */
	public static function appendXML(DOMElement $parent, $name, $address) {
		assert('is_string($name)');
		assert('is_string($address)');

		$e = $parent->ownerDocument->createElement($name);
                $parent->appendChild($e);

		$endpoint = $parent->ownerDocument->createElement('EndpointReference');
                $endpoint->setAttribute('xmlns', 'http://www.w3.org/2005/08/addressing');
		$e->appendChild($endpoint);

		$address = $parent->ownerDocument->createElement('Address', $address);
		$endpoint->appendChild($address);

		return $e;
	}

}
