<?php

/**
 * IdP implementation for SAML 2.0 protocol.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_IdP_SAML2 {

	/**
	 * Send a response to the SP.
	 *
	 * @param array $state  The authentication state.
	 */
	public static function sendResponse(array $state) {
		assert('isset($state["Attributes"])');
		assert('isset($state["SPMetadata"])');
		assert('isset($state["saml:ConsumerURL"])');
		assert('array_key_exists("saml:RequestId", $state)'); // Can be NULL
		assert('array_key_exists("saml:RelayState", $state)'); // Can be NULL.

		$spMetadata = $state["SPMetadata"];
		$spEntityId = $spMetadata['entityid'];
		$spMetadata = SimpleSAML_Configuration::loadFromArray($spMetadata,
			'$metadata[' . var_export($spEntityId, TRUE) . ']');

		SimpleSAML_Logger::info('Sending SAML 2.0 Response to ' . var_export($spEntityId, TRUE));

		$requestId = $state['saml:RequestId'];
		$relayState = $state['saml:RelayState'];
		$consumerURL = $state['saml:ConsumerURL'];
		$protocolBinding = $state['saml:Binding'];

		$idp = SimpleSAML_IdP::getByState($state);

		$idpMetadata = $idp->getConfig();

		$assertion = self::buildAssertion($idpMetadata, $spMetadata, $state);
		
		if (isset($state['saml:AuthenticatingAuthority'])) {
			$assertion->setAuthenticatingAuthority($state['saml:AuthenticatingAuthority']);
		}

		// Create the session association (for logout).
		$association = array(
			'id' => 'saml:' . $spEntityId,
			'Handler' => 'sspmod_saml_IdP_SAML2',
			'Expires' => $assertion->getSessionNotOnOrAfter(),
			'saml:entityID' => $spEntityId,
			'saml:NameID' => $state['saml:idp:NameID'],
			'saml:SessionIndex' => $assertion->getSessionIndex(),
		);

		// Maybe encrypt the assertion.
		$assertion = self::encryptAssertion($idpMetadata, $spMetadata, $assertion);

		/* Create the response. */
		$ar = self::buildResponse($idpMetadata, $spMetadata, $consumerURL);
		$ar->setInResponseTo($requestId);
		$ar->setRelayState($relayState);
		$ar->setAssertions(array($assertion));

		/* Register the session association with the IdP. */
		$idp->addAssociation($association);

		$statsData = array(
			'spEntityID' => $spEntityId,
			'idpEntityID' => $idpMetadata->getString('entityid'),
			'protocol' => 'saml2',
		);
		if (isset($state['saml:AuthnRequestReceivedAt'])) {
			$statsData['logintime'] = microtime(TRUE) - $state['saml:AuthnRequestReceivedAt'];
		}
		SimpleSAML_Stats::log('saml:idp:Response', $statsData);

		/* Send the response. */
		$binding = SAML2_Binding::getBinding($protocolBinding);
		$binding->send($ar);
	}


	/**
	 * Handle authentication error.
	 *
	 * SimpleSAML_Error_Exception $exception  The exception.
	 * @param array $state  The error state.
	 */
	public static function handleAuthError(SimpleSAML_Error_Exception $exception, array $state) {
		assert('isset($state["SPMetadata"])');
		assert('isset($state["saml:ConsumerURL"])');
		assert('array_key_exists("saml:RequestId", $state)'); // Can be NULL.
		assert('array_key_exists("saml:RelayState", $state)'); // Can be NULL.

		$spMetadata = $state["SPMetadata"];
		$spEntityId = $spMetadata['entityid'];
		$spMetadata = SimpleSAML_Configuration::loadFromArray($spMetadata,
			'$metadata[' . var_export($spEntityId, TRUE) . ']');

		$requestId = $state['saml:RequestId'];
		$relayState = $state['saml:RelayState'];
		$consumerURL = $state['saml:ConsumerURL'];
		$protocolBinding = $state['saml:Binding'];

		$idp = SimpleSAML_IdP::getByState($state);

		$idpMetadata = $idp->getConfig();

		$error = sspmod_saml_Error::fromException($exception);

		SimpleSAML_Logger::warning('Returning error to sp: ' . var_export($spEntityId, TRUE));
		$error->logWarning();

		$ar = self::buildResponse($idpMetadata, $spMetadata, $consumerURL);
		$ar->setInResponseTo($requestId);
		$ar->setRelayState($relayState);

		$status = array(
			'Code' => $error->getStatus(),
			'SubCode' => $error->getSubStatus(),
			'Message' => $error->getStatusMessage(),
		);
		$ar->setStatus($status);

		$statsData = array(
			'spEntityID' => $spEntityId,
			'idpEntityID' => $idpMetadata->getString('entityid'),
			'protocol' => 'saml2',
			'error' => $status,
		);
		if (isset($state['saml:AuthnRequestReceivedAt'])) {
			$statsData['logintime'] = microtime(TRUE) - $state['saml:AuthnRequestReceivedAt'];
		}
		SimpleSAML_Stats::log('saml:idp:Response:error', $statsData);

		$binding = SAML2_Binding::getBinding($protocolBinding);
		$binding->send($ar);
	}


	/**
	 * Find SP AssertionConsumerService based on parameter in AuthnRequest.
	 *
	 * @param array $supportedBindings  The bindings we allow for the response.
	 * @param SimpleSAML_Configuration $spMetadata  The metadata for the SP.
	 * @param string|NULL $AssertionConsumerServiceURL  AssertionConsumerServiceURL from request.
	 * @param string|NULL $ProtocolBinding  ProtocolBinding from request.
	 * @param int|NULL $AssertionConsumerServiceIndex  AssertionConsumerServiceIndex from request.
	 * @return array  Array with the Location and Binding we should use for the response.
	 */
	private static function getAssertionConsumerService(array $supportedBindings, SimpleSAML_Configuration $spMetadata,
		$AssertionConsumerServiceURL, $ProtocolBinding, $AssertionConsumerServiceIndex) {
		assert('is_string($AssertionConsumerServiceURL) || is_null($AssertionConsumerServiceURL)');
		assert('is_string($ProtocolBinding) || is_null($ProtocolBinding)');
		assert('is_int($AssertionConsumerServiceIndex) || is_null($AssertionConsumerServiceIndex)');

		/* We want to pick the best matching endpoint in the case where for example
		 * only the ProtocolBinding is given. We therefore pick endpoints with the
		 * following priority:
		 *  1. isDefault="true"
		 *  2. isDefault unset
		 *  3. isDefault="false"
		 */
		$firstNotFalse = NULL;
		$firstFalse = NULL;
		foreach ($spMetadata->getEndpoints('AssertionConsumerService') as $ep) {

			if ($AssertionConsumerServiceURL !== NULL && $ep['Location'] !== $AssertionConsumerServiceURL) {
				continue;
			}
			if ($ProtocolBinding !== NULL && $ep['Binding'] !== $ProtocolBinding) {
				continue;
			}
			if ($AssertionConsumerServiceIndex !== NULL && $ep['index'] !== $AssertionConsumerServiceIndex) {
				continue;
			}

			if (!in_array($ep['Binding'], $supportedBindings, TRUE)) {
				/* The endpoint has an unsupported binding. */
				continue;
			}

			/* We have an endpoint that matches all our requirements. Check if it is the best one. */

			if (array_key_exists('isDefault', $ep)) {
				if ($ep['isDefault'] === TRUE) {
					/* This is the first matching endpoint with isDefault set to TRUE. */
					return $ep;
				}
				/* isDefault is set to FALSE, but the endpoint is still useable. */
				if ($firstFalse === NULL) {
					/* This is the first endpoint that we can use. */
					$firstFalse = $ep;
				}
			} else if ($firstNotFalse === NULL) {
				/* This is the first endpoint without isDefault set. */
				$firstNotFalse = $ep;
			}
		}

		if ($firstNotFalse !== NULL) {
			return $firstNotFalse;
		} elseif ($firstFalse !== NULL) {
			return $firstFalse;
		}

		SimpleSAML_Logger::warning('Authentication request specifies invalid AssertionConsumerService:');
		if ($AssertionConsumerServiceURL !== NULL) {
			SimpleSAML_Logger::warning('AssertionConsumerServiceURL: ' . var_export($AssertionConsumerServiceURL, TRUE));
		}
		if ($ProtocolBinding !== NULL) {
			SimpleSAML_Logger::warning('ProtocolBinding: ' . var_export($ProtocolBinding, TRUE));
		}
		if ($AssertionConsumerServiceIndex !== NULL) {
			SimpleSAML_Logger::warning('AssertionConsumerServiceIndex: ' . var_export($AssertionConsumerServiceIndex, TRUE));
		}

		/* We have no good endpoints. Our last resort is to just use the default endpoint. */
		return $spMetadata->getDefaultEndpoint('AssertionConsumerService', $supportedBindings);
	}


	/**
	 * Receive an authentication request.
	 *
	 * @param SimpleSAML_IdP $idp  The IdP we are receiving it for.
	 */
	public static function receiveAuthnRequest(SimpleSAML_IdP $idp) {

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpMetadata = $idp->getConfig();

		$supportedBindings = array(SAML2_Const::BINDING_HTTP_POST);
		if ($idpMetadata->getBoolean('saml20.sendartifact', FALSE)) {
			$supportedBindings[] = SAML2_Const::BINDING_HTTP_ARTIFACT;
		}
		if ($idpMetadata->getBoolean('saml20.hok.assertion', FALSE)) {
			$supportedBindings[] = SAML2_Const::BINDING_HOK_SSO;
		}

		if (isset($_REQUEST['spentityid'])) {
			/* IdP initiated authentication. */

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

			$spEntityId = (string)$_REQUEST['spentityid'];
			$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');

			if (isset($_REQUEST['RelayState'])) {
				$relayState = (string)$_REQUEST['RelayState'];
			} else {
				$relayState = NULL;
			}

			if (isset($_REQUEST['binding'])){
				$protocolBinding = (string)$_REQUEST['binding'];
			} else {
				$protocolBinding = NULL;
			}

			if (isset($_REQUEST['NameIDFormat'])) {
				$nameIDFormat = (string)$_REQUEST['NameIDFormat'];
			} else {
				$nameIDFormat = NULL;
			}

			$requestId = NULL;
			$IDPList = array();
			$ProxyCount = NULL;
			$RequesterID = NULL;
			$forceAuthn = FALSE;
			$isPassive = FALSE;
			$consumerURL = NULL;
			$consumerIndex = NULL;
			$extensions = NULL;
			$allowCreate = TRUE;

			$idpInit = TRUE;

			SimpleSAML_Logger::info('SAML2.0 - IdP.SSOService: IdP initiated authentication: '. var_export($spEntityId, TRUE));

		} else {

			$binding = SAML2_Binding::getCurrentBinding();
			$request = $binding->receive();

			if (!($request instanceof SAML2_AuthnRequest)) {
				throw new SimpleSAML_Error_BadRequest('Message received on authentication request endpoint wasn\'t an authentication request.');
			}

			$spEntityId = $request->getIssuer();
			if ($spEntityId === NULL) {
				throw new SimpleSAML_Error_BadRequest('Received message on authentication request endpoint without issuer.');
			}
			$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');

			sspmod_saml_Message::validateMessage($spMetadata, $idpMetadata, $request);

			$relayState = $request->getRelayState();

			$requestId = $request->getId();
			$IDPList = $request->getIDPList();
			$ProxyCount = $request->getProxyCount();
			if ($ProxyCount !== null) $ProxyCount--;
			$RequesterID = $request->getRequesterID();
			$forceAuthn = $request->getForceAuthn();
			$isPassive = $request->getIsPassive();
			$consumerURL = $request->getAssertionConsumerServiceURL();
			$protocolBinding = $request->getProtocolBinding();
			$consumerIndex = $request->getAssertionConsumerServiceIndex();
			$extensions = $request->getExtensions();

			$nameIdPolicy = $request->getNameIdPolicy();
			if (isset($nameIdPolicy['Format'])) {
				$nameIDFormat = $nameIdPolicy['Format'];
			} else {
				$nameIDFormat = NULL;
			}
			if (isset($nameIdPolicy['AllowCreate'])) {
				$allowCreate = $nameIdPolicy['AllowCreate'];
			} else {
				$allowCreate = FALSE;
			}

			$idpInit = FALSE;

			SimpleSAML_Logger::info('SAML2.0 - IdP.SSOService: incoming authentication request: '. var_export($spEntityId, TRUE));
		}

		SimpleSAML_Stats::log('saml:idp:AuthnRequest', array(
			'spEntityID' => $spEntityId,
			'idpEntityID' => $idpMetadata->getString('entityid'),
			'forceAuthn' => $forceAuthn,
			'isPassive' => $isPassive,
			'protocol' => 'saml2',
			'idpInit' => $idpInit,
		));

		$acsEndpoint = self::getAssertionConsumerService($supportedBindings, $spMetadata, $consumerURL, $protocolBinding, $consumerIndex);

		$IDPList = array_unique(array_merge($IDPList, $spMetadata->getArrayizeString('IDPList', array())));
		if ($ProxyCount === null) $ProxyCount = $spMetadata->getInteger('ProxyCount', null);

		if (!$forceAuthn) {
			$forceAuthn = $spMetadata->getBoolean('ForceAuthn', FALSE);
		}

		$sessionLostParams = array(
			'spentityid' => $spEntityId,
			'cookieTime' => time(),
		);
		if ($relayState !== NULL) {
			$sessionLostParams['RelayState'] = $relayState;
		}

		$sessionLostURL = \SimpleSAML\Utils\HTTP::addURLParameters(
            \SimpleSAML\Utils\HTTP::getSelfURLNoQuery(),
			$sessionLostParams);

		$state = array(
			'Responder' => array('sspmod_saml_IdP_SAML2', 'sendResponse'),
			SimpleSAML_Auth_State::EXCEPTION_HANDLER_FUNC => array('sspmod_saml_IdP_SAML2', 'handleAuthError'),
			SimpleSAML_Auth_State::RESTART => $sessionLostURL,

			'SPMetadata' => $spMetadata->toArray(),
			'saml:RelayState' => $relayState,
			'saml:RequestId' => $requestId,
			'saml:IDPList' => $IDPList,
			'saml:ProxyCount' => $ProxyCount,
			'saml:RequesterID' => $RequesterID,
			'ForceAuthn' => $forceAuthn,
			'isPassive' => $isPassive,
			'saml:ConsumerURL' => $acsEndpoint['Location'],
			'saml:Binding' => $acsEndpoint['Binding'],
			'saml:NameIDFormat' => $nameIDFormat,
			'saml:AllowCreate' => $allowCreate,
			'saml:Extensions' => $extensions,
			'saml:AuthnRequestReceivedAt' => microtime(TRUE),
		);

		$idp->handleAuthenticationRequest($state);
	}


	/**
	 * Send a logout request to a given association.
	 *
	 * @param SimpleSAML_IdP $idp  The IdP we are sending a logout request from.
	 * @param array $association  The association that should be terminated.
	 * @param string|NULL $relayState  An id that should be carried across the logout.
	 */
	public static function sendLogoutRequest(SimpleSAML_IdP $idp, array $association, $relayState) {
		assert('is_string($relayState) || is_null($relayState)');

		SimpleSAML_Logger::info('Sending SAML 2.0 LogoutRequest to: '. var_export($association['saml:entityID'], TRUE));

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpMetadata = $idp->getConfig();
		$spMetadata = $metadata->getMetaDataConfig($association['saml:entityID'], 'saml20-sp-remote');

		SimpleSAML_Stats::log('saml:idp:LogoutRequest:sent', array(
			'spEntityID' => $association['saml:entityID'],
			'idpEntityID' => $idpMetadata->getString('entityid'),
		));

		$dst = $spMetadata->getEndpointPrioritizedByBinding('SingleLogoutService', array(
			SAML2_Const::BINDING_HTTP_REDIRECT,
			SAML2_Const::BINDING_HTTP_POST)
		);
		$binding = SAML2_Binding::getBinding($dst['Binding']);
		$lr = self::buildLogoutRequest($idpMetadata, $spMetadata, $association, $relayState);
		$lr->setDestination($dst['Location']);

		$binding->send($lr);
	}


	/**
	 * Send a logout response.
	 *
	 * @param SimpleSAML_IdP $idp  The IdP we are sending a logout request from.
	 * @param array &$state  The logout state array.
	 */
	public static function sendLogoutResponse(SimpleSAML_IdP $idp, array $state) {
		assert('isset($state["saml:SPEntityId"])');
		assert('isset($state["saml:RequestId"])');
		assert('array_key_exists("saml:RelayState", $state)'); // Can be NULL.

		$spEntityId = $state['saml:SPEntityId'];

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpMetadata = $idp->getConfig();
		$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');

		$lr = sspmod_saml_Message::buildLogoutResponse($idpMetadata, $spMetadata);
		$lr->setInResponseTo($state['saml:RequestId']);
		$lr->setRelayState($state['saml:RelayState']);

		if (isset($state['core:Failed']) && $state['core:Failed']) {
			$partial = TRUE;
			$lr->setStatus(array(
				'Code' => SAML2_Const::STATUS_SUCCESS,
				'SubCode' => SAML2_Const::STATUS_PARTIAL_LOGOUT,
			));
			SimpleSAML_Logger::info('Sending logout response for partial logout to SP ' . var_export($spEntityId, TRUE));
		} else {
			$partial = FALSE;
			SimpleSAML_Logger::debug('Sending logout response to SP ' . var_export($spEntityId, TRUE));
		}

		SimpleSAML_Stats::log('saml:idp:LogoutResponse:sent', array(
			'spEntityID' => $spEntityId,
			'idpEntityID' => $idpMetadata->getString('entityid'),
			'partial' => $partial
		));
		$dst = $spMetadata->getEndpointPrioritizedByBinding('SingleLogoutService', array(
			SAML2_Const::BINDING_HTTP_REDIRECT,
			SAML2_Const::BINDING_HTTP_POST)
		);
		$binding = SAML2_Binding::getBinding($dst['Binding']);
		if (isset($dst['ResponseLocation'])) {
			$dst = $dst['ResponseLocation'];
		} else {
			$dst = $dst['Location'];
		}
		$lr->setDestination($dst);

		$binding->send($lr);
	}


	/**
	 * Receive a logout message.
	 *
	 * @param SimpleSAML_IdP $idp  The IdP we are receiving it for.
	 */
	public static function receiveLogoutMessage(SimpleSAML_IdP $idp) {

		$binding = SAML2_Binding::getCurrentBinding();
		$message = $binding->receive();

		$spEntityId = $message->getIssuer();
		if ($spEntityId === NULL) {
			/* Without an issuer we have no way to respond to the message. */
			throw new SimpleSAML_Error_BadRequest('Received message on logout endpoint without issuer.');
		}

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpMetadata = $idp->getConfig();
		$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');

		sspmod_saml_Message::validateMessage($spMetadata, $idpMetadata, $message);

		if ($message instanceof SAML2_LogoutResponse) {

			SimpleSAML_Logger::info('Received SAML 2.0 LogoutResponse from: '. var_export($spEntityId, TRUE));
			$statsData = array(
				'spEntityID' => $spEntityId,
				'idpEntityID' => $idpMetadata->getString('entityid'),
			);
			if (!$message->isSuccess()) {
				$statsData['error'] = $message->getStatus();
			}
			SimpleSAML_Stats::log('saml:idp:LogoutResponse:recv', $statsData);

			$relayState = $message->getRelayState();

			if (!$message->isSuccess()) {
				$logoutError = sspmod_saml_Message::getResponseError($message);
				SimpleSAML_Logger::warning('Unsuccessful logout. Status was: ' . $logoutError);
			} else {
				$logoutError = NULL;
			}

			$assocId = 'saml:' . $spEntityId;

			$idp->handleLogoutResponse($assocId, $relayState, $logoutError);


		} elseif ($message instanceof SAML2_LogoutRequest) {

			SimpleSAML_Logger::info('Received SAML 2.0 LogoutRequest from: '. var_export($spEntityId, TRUE));
			SimpleSAML_Stats::log('saml:idp:LogoutRequest:recv', array(
				'spEntityID' => $spEntityId,
				'idpEntityID' => $idpMetadata->getString('entityid'),
			));

			$spStatsId = $spMetadata->getString('core:statistics-id', $spEntityId);
			SimpleSAML_Logger::stats('saml20-idp-SLO spinit ' . $spStatsId . ' ' . $idpMetadata->getString('entityid'));

			$state = array(
				'Responder' => array('sspmod_saml_IdP_SAML2', 'sendLogoutResponse'),
				'saml:SPEntityId' => $spEntityId,
				'saml:RelayState' => $message->getRelayState(),
				'saml:RequestId' => $message->getId(),
			);

			$assocId = 'saml:' . $spEntityId;
			$idp->handleLogoutRequest($state, $assocId);

		} else {
			throw new SimpleSAML_Error_BadRequest('Unknown message received on logout endpoint: ' . get_class($message));
		}

	}


	/**
     * Retrieve a logout URL for a given logout association.
	 *
	 * @param SimpleSAML_IdP $idp  The IdP we are sending a logout request from.
	 * @param array $association  The association that should be terminated.
	 * @param string|NULL $relayState  An id that should be carried across the logout.
	 */
	public static function getLogoutURL(SimpleSAML_IdP $idp, array $association, $relayState) {
		assert('is_string($relayState) || is_null($relayState)');

		SimpleSAML_Logger::info('Sending SAML 2.0 LogoutRequest to: '. var_export($association['saml:entityID'], TRUE));

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpMetadata = $idp->getConfig();
		$spMetadata = $metadata->getMetaDataConfig($association['saml:entityID'], 'saml20-sp-remote');

		$bindings = array(SAML2_Const::BINDING_HTTP_REDIRECT,
						  SAML2_Const::BINDING_HTTP_POST);
		$dst = $spMetadata->getEndpointPrioritizedByBinding('SingleLogoutService', $bindings);

		if ($dst['Binding'] === SAML2_Const::BINDING_HTTP_POST) {
			$params = array('association' => $association['id'], 'idp' => $idp->getId());
			if ($relayState !== NULL) {
				$params['RelayState'] = $relayState;
			}
			return SimpleSAML_Module::getModuleURL('core/idp/logout-iframe-post.php', $params);
		}

		$lr = self::buildLogoutRequest($idpMetadata, $spMetadata, $association, $relayState);
		$lr->setDestination($dst['Location']);

		$binding = new SAML2_HTTPRedirect();
		return $binding->getRedirectURL($lr);
	}


	/**
	 * Retrieve the metadata for the given SP association.
	 *
	 * @param SimpleSAML_IdP $idp  The IdP the association belongs to.
	 * @param array $association  The SP association.
	 * @return SimpleSAML_Configuration  Configuration object for the SP metadata.
	 */
	public static function getAssociationConfig(SimpleSAML_IdP $idp, array $association) {
		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		try {
			return $metadata->getMetaDataConfig($association['saml:entityID'], 'saml20-sp-remote');
		} catch (Exception $e) {
			return SimpleSAML_Configuration::loadFromArray(array(), 'Unknown SAML 2 entity.');
		}
	}


	/**
	 * Calculate the NameID value that should be used.
	 *
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the IdP.
	 * @param SimpleSAML_Configuration $dstMetadata  The metadata of the SP.
	 * @param array &$state  The authentication state of the user.
	 * @return string  The NameID value.
	 */
	private static function generateNameIdValue(SimpleSAML_Configuration $idpMetadata,
		SimpleSAML_Configuration $spMetadata, array &$state) {

		$attribute = $spMetadata->getString('simplesaml.nameidattribute', NULL);
		if ($attribute === NULL) {
			$attribute = $idpMetadata->getString('simplesaml.nameidattribute', NULL);
			if ($attribute === NULL) {
				if (!isset($state['UserID'])) {
					SimpleSAML_Logger::error('Unable to generate NameID. Check the userid.attribute option.');
					return NULL;
				}
				$attributeValue = $state['UserID'];
				$idpEntityId = $idpMetadata->getString('entityid');
				$spEntityId = $spMetadata->getString('entityid');

				$secretSalt = SimpleSAML\Utils\Config::getSecretSalt();

				$uidData = 'uidhashbase' . $secretSalt;
				$uidData .= strlen($idpEntityId) . ':' . $idpEntityId;
				$uidData .= strlen($spEntityId) . ':' . $spEntityId;
				$uidData .= strlen($attributeValue) . ':' . $attributeValue;
				$uidData .= $secretSalt;

				return hash('sha1', $uidData);
			}
		}

		$attributes = $state['Attributes'];
		if (!array_key_exists($attribute, $attributes)) {
			SimpleSAML_Logger::error('Unable to add NameID: Missing ' . var_export($attribute, TRUE) .
				' in the attributes of the user.');
			return NULL;
		}

		return $attributes[$attribute][0];
	}


	/**
	 * Helper function for encoding attributes.
	 *
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the IdP.
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the SP.
	 * @param array $attributes  The attributes of the user
	 * @return array  The encoded attributes.
	 */
	private static function encodeAttributes(SimpleSAML_Configuration $idpMetadata,
		SimpleSAML_Configuration $spMetadata, array $attributes) {

		$base64Attributes = $spMetadata->getBoolean('base64attributes', NULL);
		if ($base64Attributes === NULL) {
			$base64Attributes = $idpMetadata->getBoolean('base64attributes', FALSE);
		}

		if ($base64Attributes) {
			$defaultEncoding = 'base64';
		} else {
			$defaultEncoding = 'string';
		}

		$srcEncodings = $idpMetadata->getArray('attributeencodings', array());
		$dstEncodings = $spMetadata->getArray('attributeencodings', array());

		/*
		 * Merge the two encoding arrays. Encodings specified in the target metadata
		 * takes precedence over the source metadata.
		 */
		$encodings = array_merge($srcEncodings, $dstEncodings);

		$ret = array();
		foreach ($attributes as $name => $values) {
			$ret[$name] = array();
			if (array_key_exists($name, $encodings)) {
				$encoding = $encodings[$name];
			} else {
				$encoding = $defaultEncoding;
			}

			foreach ($values as $value) {
                // allow null values
                if ($value === null) {
                    $ret[$name][] = $value;
                    continue;
                }

				$attrval = $value;
				if ($value instanceof DOMNodeList) {
					$attrval = new SAML2_XML_saml_AttributeValue($value->item(0)->parentNode);
				}

				switch ($encoding) {
				case 'string':
					$value = (string)$attrval;
					break;
				case 'base64':
					$value = base64_encode((string)$attrval);
					break;
				case 'raw':
					if (is_string($value)) {
						$doc = SAML2_DOMDocumentFactory::fromString('<root>' . $value . '</root>');
						$value = $doc->firstChild->childNodes;
					}
					assert('$value instanceof DOMNodeList');
					break;
				default:
					throw new SimpleSAML_Error_Exception('Invalid encoding for attribute ' .
						var_export($name, TRUE) . ': ' . var_export($encoding, TRUE));
				}
				$ret[$name][] = $value;
			}
		}

		return $ret;
	}


	/**
	 * Determine which NameFormat we should use for attributes.
	 *
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the IdP.
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the SP.
	 * @return string  The NameFormat.
	 */
	private static function getAttributeNameFormat(SimpleSAML_Configuration $idpMetadata,
		SimpleSAML_Configuration $spMetadata) {

		/* Try SP metadata first. */
		$attributeNameFormat = $spMetadata->getString('attributes.NameFormat', NULL);
		if ($attributeNameFormat !== NULL) {
			return $attributeNameFormat;
		}
		$attributeNameFormat = $spMetadata->getString('AttributeNameFormat', NULL);
		if ($attributeNameFormat !== NULL) {
			return $attributeNameFormat;
		}

		/* Look in IdP metadata. */
		$attributeNameFormat = $idpMetadata->getString('attributes.NameFormat', NULL);
		if ($attributeNameFormat !== NULL) {
			return $attributeNameFormat;
		}
		$attributeNameFormat = $idpMetadata->getString('AttributeNameFormat', NULL);
		if ($attributeNameFormat !== NULL) {
			return $attributeNameFormat;
		}

		/* Default. */
		return 'urn:oasis:names:tc:SAML:2.0:attrname-format:basic';
	}


	/**
	 * Build an assertion based on information in the metadata.
	 *
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the IdP.
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the SP.
	 * @param array &$state  The state array with information about the request.
	 * @return SAML2_Assertion  The assertion.
	 */
	private static function buildAssertion(SimpleSAML_Configuration $idpMetadata,
		SimpleSAML_Configuration $spMetadata, array &$state) {
		assert('isset($state["Attributes"])');
		assert('isset($state["saml:ConsumerURL"])');

		$now = time();

		$signAssertion = $spMetadata->getBoolean('saml20.sign.assertion', NULL);
		if ($signAssertion === NULL) {
			$signAssertion = $idpMetadata->getBoolean('saml20.sign.assertion', TRUE);
		}

		$config = SimpleSAML_Configuration::getInstance();

		$a = new SAML2_Assertion();
		if ($signAssertion) {
			sspmod_saml_Message::addSign($idpMetadata, $spMetadata, $a);
		}

		$a->setIssuer($idpMetadata->getString('entityid'));
		$a->setValidAudiences(array($spMetadata->getString('entityid')));

		$a->setNotBefore($now - 30);

		$assertionLifetime = $spMetadata->getInteger('assertion.lifetime', NULL);
		if ($assertionLifetime === NULL) {
			$assertionLifetime = $idpMetadata->getInteger('assertion.lifetime', 300);
		}
		$a->setNotOnOrAfter($now + $assertionLifetime);

		if (isset($state['saml:AuthnContextClassRef'])) {
			$a->setAuthnContext($state['saml:AuthnContextClassRef']);
		} else {
			$a->setAuthnContext(SAML2_Const::AC_PASSWORD);
		}

		$sessionStart = $now;
		if (isset($state['AuthnInstant'])) {
			$a->setAuthnInstant($state['AuthnInstant']);
			$sessionStart = $state['AuthnInstant'];
		}

		$sessionLifetime = $config->getInteger('session.duration', 8*60*60);
		$a->setSessionNotOnOrAfter($sessionStart + $sessionLifetime);

		$a->setSessionIndex(SimpleSAML\Utils\Random::generateID());

		$sc = new SAML2_XML_saml_SubjectConfirmation();
		$sc->SubjectConfirmationData = new SAML2_XML_saml_SubjectConfirmationData();
		$sc->SubjectConfirmationData->NotOnOrAfter = $now + $assertionLifetime;
		$sc->SubjectConfirmationData->Recipient = $state['saml:ConsumerURL'];
		$sc->SubjectConfirmationData->InResponseTo = $state['saml:RequestId'];

		/* ProtcolBinding of SP's <AuthnRequest> overwrites IdP hosted metadata configuration. */
		$hokAssertion = NULL;
		if ($state['saml:Binding'] === SAML2_Const::BINDING_HOK_SSO) {
		    $hokAssertion = TRUE;
		}
		if ($hokAssertion === NULL) {
			$hokAssertion = $idpMetadata->getBoolean('saml20.hok.assertion', FALSE);
		}

		if ($hokAssertion) {
			/* Holder-of-Key */
			$sc->Method = SAML2_Const::CM_HOK;
			if (\SimpleSAML\Utils\HTTP::isHTTPS()) {
				if (isset($_SERVER['SSL_CLIENT_CERT']) && !empty($_SERVER['SSL_CLIENT_CERT'])) {
					/* Extract certificate data (if this is a certificate). */
					$clientCert = $_SERVER['SSL_CLIENT_CERT'];
					$pattern = '/^-----BEGIN CERTIFICATE-----([^-]*)^-----END CERTIFICATE-----/m';
					if (preg_match($pattern, $clientCert, $matches)) {
						/* We have a client certificate from the browser which we add to the HoK assertion. */
						$x509Certificate = new SAML2_XML_ds_X509Certificate();
						$x509Certificate->certificate = str_replace(array("\r", "\n", " "), '', $matches[1]);

						$x509Data = new SAML2_XML_ds_X509Data();
						$x509Data->data[] = $x509Certificate;

						$keyInfo = new SAML2_XML_ds_KeyInfo();
						$keyInfo->info[] = $x509Data;

						$sc->SubjectConfirmationData->info[] = $keyInfo;
					} else throw new SimpleSAML_Error_Exception('Error creating HoK assertion: No valid client certificate provided during TLS handshake with IdP');
				} else throw new SimpleSAML_Error_Exception('Error creating HoK assertion: No client certificate provided during TLS handshake with IdP');
			} else throw new SimpleSAML_Error_Exception('Error creating HoK assertion: No HTTPS connection to IdP, but required for Holder-of-Key SSO');
		} else {
			/* Bearer */
			$sc->Method = SAML2_Const::CM_BEARER;
		}
		$a->setSubjectConfirmation(array($sc));

		/* Add attributes. */

		if ($spMetadata->getBoolean('simplesaml.attributes', TRUE)) {
			$attributeNameFormat = self::getAttributeNameFormat($idpMetadata, $spMetadata);
			$a->setAttributeNameFormat($attributeNameFormat);
			$attributes = self::encodeAttributes($idpMetadata, $spMetadata, $state['Attributes']);
			$a->setAttributes($attributes);
		}


		/* Generate the NameID for the assertion. */

		if (isset($state['saml:NameIDFormat'])) {
			$nameIdFormat = $state['saml:NameIDFormat'];
		} else {
			$nameIdFormat = NULL;
		}

		if ($nameIdFormat === NULL || !isset($state['saml:NameID'][$nameIdFormat])) {
			/* Either not set in request, or not set to a format we supply. Fall back to old generation method. */
			$nameIdFormat = $spMetadata->getString('NameIDFormat', NULL);
			if ($nameIdFormat === NULL) {
				$nameIdFormat = $idpMetadata->getString('NameIDFormat', SAML2_Const::NAMEID_TRANSIENT);
			}
		}

		if (isset($state['saml:NameID'][$nameIdFormat])) {
			$nameId = $state['saml:NameID'][$nameIdFormat];
			$nameId['Format'] = $nameIdFormat;
		} else {
			$spNameQualifier = $spMetadata->getString('SPNameQualifier', NULL);
			if ($spNameQualifier === NULL) {
				$spNameQualifier = $spMetadata->getString('entityid');
			}

			if ($nameIdFormat === SAML2_Const::NAMEID_TRANSIENT) {
				/* generate a random id */
				$nameIdValue = SimpleSAML\Utils\Random::generateID();
			} else {
				/* this code will end up generating either a fixed assigned id (via nameid.attribute)
				   or random id if not assigned/configured */
				$nameIdValue = self::generateNameIdValue($idpMetadata, $spMetadata, $state);
				if ($nameIdValue === NULL) {
					SimpleSAML_Logger::warning('Falling back to transient NameID.');
					$nameIdFormat = SAML2_Const::NAMEID_TRANSIENT;
					$nameIdValue = SimpleSAML\Utils\Random::generateID();
				}
			}

			$nameId = array(
				'Format' => $nameIdFormat,
				'Value' => $nameIdValue,
				'SPNameQualifier' => $spNameQualifier,
			);
		}

		$state['saml:idp:NameID'] = $nameId;

		$a->setNameId($nameId);

		$encryptNameId = $spMetadata->getBoolean('nameid.encryption', NULL);
		if ($encryptNameId === NULL) {
			$encryptNameId = $idpMetadata->getBoolean('nameid.encryption', FALSE);
		}
		if ($encryptNameId) {
			$a->encryptNameId(sspmod_saml_Message::getEncryptionKey($spMetadata));
		}

		return $a;
	}


	/**
	 * Encrypt an assertion.
	 *
	 * This function takes in a SAML2_Assertion and encrypts it if encryption of
	 * assertions are enabled in the metadata.
	 *
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the IdP.
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the SP.
	 * @param SAML2_Assertion $assertion  The assertion we are encrypting.
	 * @return SAML2_Assertion|SAML2_EncryptedAssertion  The assertion.
	 */
	private static function encryptAssertion(SimpleSAML_Configuration $idpMetadata,
		SimpleSAML_Configuration $spMetadata, SAML2_Assertion $assertion) {

		$encryptAssertion = $spMetadata->getBoolean('assertion.encryption', NULL);
		if ($encryptAssertion === NULL) {
			$encryptAssertion = $idpMetadata->getBoolean('assertion.encryption', FALSE);
		}
		if (!$encryptAssertion) {
			/* We are _not_ encrypting this assertion, and are therefore done. */
			return $assertion;
		}


		$sharedKey = $spMetadata->getString('sharedkey', NULL);
		if ($sharedKey !== NULL) {
			$key = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
			$key->loadKey($sharedKey);
		} else {
			$keys = $spMetadata->getPublicKeys('encryption', TRUE);
			$key = $keys[0];
			switch ($key['type']) {
			case 'X509Certificate':
				$pemKey = "-----BEGIN CERTIFICATE-----\n" .
					chunk_split($key['X509Certificate'], 64) .
					"-----END CERTIFICATE-----\n";
				break;
			default:
				throw new SimpleSAML_Error_Exception('Unsupported encryption key type: ' . $key['type']);
			}

			/* Extract the public key from the certificate for encryption. */
			$key = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type'=>'public'));
			$key->loadKey($pemKey);
		}

		$ea = new SAML2_EncryptedAssertion();
		$ea->setAssertion($assertion, $key);
		return $ea;
	}


	/**
	 * Build a logout request based on information in the metadata.
	 *
	 * @param SimpleSAML_Configuration idpMetadata  The metadata of the IdP.
	 * @param SimpleSAML_Configuration spMetadata  The metadata of the SP.
	 * @param array $association  The SP association.
	 * @param string|NULL $relayState  An id that should be carried across the logout.
	 */
	private static function buildLogoutRequest(SimpleSAML_Configuration $idpMetadata,
		SimpleSAML_Configuration $spMetadata, array $association, $relayState) {

		$lr = sspmod_saml_Message::buildLogoutRequest($idpMetadata, $spMetadata);
		$lr->setRelayState($relayState);
		$lr->setSessionIndex($association['saml:SessionIndex']);
		$lr->setNameId($association['saml:NameID']);

		$assertionLifetime = $spMetadata->getInteger('assertion.lifetime', NULL);
		if ($assertionLifetime === NULL) {
			$assertionLifetime = $idpMetadata->getInteger('assertion.lifetime', 300);
		}
		$lr->setNotOnOrAfter(time() + $assertionLifetime);

		$encryptNameId = $spMetadata->getBoolean('nameid.encryption', NULL);
		if ($encryptNameId === NULL) {
			$encryptNameId = $idpMetadata->getBoolean('nameid.encryption', FALSE);
		}
		if ($encryptNameId) {
			$lr->encryptNameId(sspmod_saml_Message::getEncryptionKey($spMetadata));
		}

		return $lr;
	}


	/**
	 * Build a authentication response based on information in the metadata.
	 *
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the IdP.
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the SP.
	 * @param string $consumerURL  The Destination URL of the response.
	 */
	private static function buildResponse(SimpleSAML_Configuration $idpMetadata,
		SimpleSAML_Configuration $spMetadata, $consumerURL) {

		$signResponse = $spMetadata->getBoolean('saml20.sign.response', NULL);
		if ($signResponse === NULL) {
			$signResponse = $idpMetadata->getBoolean('saml20.sign.response', TRUE);
		}

		$r = new SAML2_Response();

		$r->setIssuer($idpMetadata->getString('entityid'));
		$r->setDestination($consumerURL);

		if ($signResponse) {
			sspmod_saml_Message::addSign($idpMetadata, $spMetadata, $r);
		}

		return $r;
	}

}