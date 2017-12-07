<?php
/**
 * Class representing fed TokenTypesOffered.
 *
 * @package SimpleSAMLphp
 */
class sspmod_adfs_SAML2_XML_fed_TokenTypesOffered {
	/**
	 * Add tokentypesoffered to an XML element.
	 *
	 * @param DOMElement $parent  The element we should append this endpoint to.
	 */
	public static function appendXML(DOMElement $parent) {

		$e = $parent->ownerDocument->createElementNS(sspmod_adfs_SAML2_XML_fed_Const::NS_FED, 'fed:TokenTypesOffered');
                $parent->appendChild($e);

		$tokentype = $parent->ownerDocument->createElementNS(sspmod_adfs_SAML2_XML_fed_Const::NS_FED, 'fed:TokenType');
                $tokentype->setAttribute('Uri', 'urn:oasis:names:tc:SAML:1.0:assertion');
		$e->appendChild($tokentype);

		return $e;
	}

}
