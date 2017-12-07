<?php

/**
 * IdP implementation for SAML 1.1 protocol.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_IdP_SAML1 {

	/**
	 * Send a response to the SP.
	 *
	 * @param array $state  The authentication state.
	 */
	public static function sendResponse(array $state) {
		assert('isset($state["Attributes"])');
		assert('isset($state["SPMetadata"])');
		assert('isset($state["saml:shire"])');
		assert('array_key_exists("saml:target", $state)'); // Can be NULL

		$spMetadata = $state["SPMetadata"];
		$spEntityId = $spMetadata['entityid'];
		$spMetadata = SimpleSAML_Configuration::loadFromArray($spMetadata,
			'$metadata[' . var_export($spEntityId, TRUE) . ']');

		SimpleSAML_Logger::info('Sending SAML 1.1 Response to ' . var_export($spEntityId, TRUE));

		$attributes = $state['Attributes'];
		$shire = $state['saml:shire'];
		$target = $state['saml:target'];

		$idp = SimpleSAML_IdP::getByState($state);

		$idpMetadata = $idp->getConfig();

		$config = SimpleSAML_Configuration::getInstance();
		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();

		$statsData = array(
			'spEntityID' => $spEntityId,
			'idpEntityID' => $idpMetadata->getString('entityid'),
			'protocol' => 'saml1',
		);
		if (isset($state['saml:AuthnRequestReceivedAt'])) {
			$statsData['logintime'] = microtime(TRUE) - $state['saml:AuthnRequestReceivedAt'];
		}
		SimpleSAML_Stats::log('saml:idp:Response', $statsData);

		// Generate and send response.
		$ar = new SimpleSAML_XML_Shib13_AuthnResponse();
		$authnResponseXML = $ar->generate($idpMetadata, $spMetadata, $shire, $attributes);

		$httppost = new SimpleSAML_Bindings_Shib13_HTTPPost($config, $metadata);
		$httppost->sendResponse($authnResponseXML, $idpMetadata, $spMetadata, $target, $shire);
	}


	/**
	 * Receive an authentication request.
	 *
	 * @param SimpleSAML_IdP $idp  The IdP we are receiving it for.
	 */
	public static function receiveAuthnRequest(SimpleSAML_IdP $idp) {

		if (isset($_REQUEST['cookieTime'])) {
			$cookieTime = (int)$_REQUEST['cookieTime'];
			if ($cookieTime + 5 > time()) {
				/*
				 * Less than five seconds has passed since we were
				 * here the last time. Cookies are probably disabled.
				 */
				\SimpleSAML\Utils\HTTP::checkSessionCookie(\SimpleSAML\Utils\HTTP::getSelfURL());
			}
		}

		if (!isset($_REQUEST['providerId'])) {
			throw new SimpleSAML_Error_BadRequest('Missing providerId parameter.');
		}
		$spEntityId = (string)$_REQUEST['providerId'];

		if (!isset($_REQUEST['shire'])) {
			throw new SimpleSAML_Error_BadRequest('Missing shire parameter.');
		}
		$shire = (string)$_REQUEST['shire'];

		if (isset($_REQUEST['target'])) {
			$target = $_REQUEST['target'];
		} else {
			$target = NULL;
		}

		SimpleSAML_Logger::info('Shib1.3 - IdP.SSOService: Got incoming Shib authnRequest from ' . var_export($spEntityId, TRUE) . '.');

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'shib13-sp-remote');

		$found = FALSE;
		foreach ($spMetadata->getEndpoints('AssertionConsumerService') as $ep) {
			if ($ep['Binding'] !== 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post') {
				continue;
			}
			if ($ep['Location'] !== $shire) {
				continue;
			}
			$found = TRUE;
			break;
		}
		if (!$found) {
			throw new Exception('Invalid AssertionConsumerService for SP ' .
				var_export($spEntityId, TRUE) . ': ' . var_export($shire, TRUE));
		}

		SimpleSAML_Stats::log('saml:idp:AuthnRequest', array(
			'spEntityID' => $spEntityId,
			'protocol' => 'saml1',
		));

		$sessionLostURL = \SimpleSAML\Utils\HTTP::addURLParameters(
            \SimpleSAML\Utils\HTTP::getSelfURL(),
			array('cookieTime' => time()));

		$state = array(
			'Responder' => array('sspmod_saml_IdP_SAML1', 'sendResponse'),
			'SPMetadata' => $spMetadata->toArray(),
			SimpleSAML_Auth_State::RESTART => $sessionLostURL,
			'saml:shire' => $shire,
			'saml:target' => $target,
			'saml:AuthnRequestReceivedAt' => microtime(TRUE),
		);

		$idp->handleAuthenticationRequest($state);
	}

}
