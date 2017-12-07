<?php

/**
 * The Shibboleth 1.3 Authentication Request. Not part of SAML 1.1, 
 * but an extension using query paramters no XML.
 *
 * @author Andreas Åkre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class SimpleSAML_XML_Shib13_AuthnRequest {

	private $issuer = null;
	private $relayState = null;

	public function setRelayState($relayState) {
		$this->relayState = $relayState;
	}
	
	public function getRelayState() {
		return $this->relayState;
	}
	
	public function setIssuer($issuer) {
		$this->issuer = $issuer;
	}
	public function getIssuer() {
		return $this->issuer;
	}

	public function createRedirect($destination, $shire) {
		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpmetadata = $metadata->getMetaDataConfig($destination, 'shib13-idp-remote');

		$desturl = $idpmetadata->getDefaultEndpoint('SingleSignOnService', array('urn:mace:shibboleth:1.0:profiles:AuthnRequest'));
		$desturl = $desturl['Location'];

		$target = $this->getRelayState();
		
		$url = $desturl . '?' .
	    	'providerId=' . urlencode($this->getIssuer()) .
		    '&shire=' . urlencode($shire) .
		    (isset($target) ? '&target=' . urlencode($target) : '');
		return $url;
	}

}
