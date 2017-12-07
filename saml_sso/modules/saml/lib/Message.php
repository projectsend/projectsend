<?php


/**
 * Common code for building SAML 2 messages based on the
 * available metadata.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_Message {

	/**
	 * Add signature key and sender certificate to an element (Message or Assertion).
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender.
	 * @param SimpleSAML_Configuration $dstMetadata  The metadata of the recipient.
	 * @param SAML2_Message $element  The element we should add the data to.
	 */
	public static function addSign(SimpleSAML_Configuration $srcMetadata, SimpleSAML_Configuration $dstMetadata, SAML2_SignedElement $element) {

		$dstPrivateKey = $dstMetadata->getString('signature.privatekey', NULL);

		if ($dstPrivateKey !== NULL) {
			$keyArray = SimpleSAML\Utils\Crypto::loadPrivateKey($dstMetadata, TRUE, 'signature.');
			$certArray = SimpleSAML\Utils\Crypto::loadPublicKey($dstMetadata, FALSE, 'signature.');
		} else {
			$keyArray = SimpleSAML\Utils\Crypto::loadPrivateKey($srcMetadata, TRUE);
			$certArray = SimpleSAML\Utils\Crypto::loadPublicKey($srcMetadata, FALSE);
		}

		$algo = $dstMetadata->getString('signature.algorithm', NULL);
		if ($algo === NULL) {
			/*
			 * In the NIST Special Publication 800-131A, SHA-1 became deprecated for generating
			 * new digital signatures in 2011, and will be explicitly disallowed starting the 1st
			 * of January, 2014. We'll keep this as a default for the next release and mark it
			 * as deprecated, as part of the transition to SHA-256.
			 *
			 * See http://csrc.nist.gov/publications/nistpubs/800-131A/sp800-131A.pdf for more info.
			 *
			 * TODO: change default to XMLSecurityKey::RSA_SHA256.
			 */
			$algo = $srcMetadata->getString('signature.algorithm', XMLSecurityKey::RSA_SHA1);
		}

		$privateKey = new XMLSecurityKey($algo, array('type' => 'private'));
		if (array_key_exists('password', $keyArray)) {
			$privateKey->passphrase = $keyArray['password'];
		}
		$privateKey->loadKey($keyArray['PEM'], FALSE);

		$element->setSignatureKey($privateKey);

		if ($certArray === NULL) {
			// We don't have a certificate to add
			return;
		}

		if (!array_key_exists('PEM', $certArray)) {
			// We have a public key with only a fingerprint.
			return;
		}

		$element->setCertificates(array($certArray['PEM']));
	}


	/**
	 * Add signature key and and senders certificate to message.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender.
	 * @param SimpleSAML_Configuration $dstMetadata  The metadata of the recipient.
	 * @param SAML2_Message $message  The message we should add the data to.
	 */
	private static function addRedirectSign(SimpleSAML_Configuration $srcMetadata, SimpleSAML_Configuration $dstMetadata, SAML2_message $message) {

		if ($message instanceof SAML2_LogoutRequest || $message instanceof SAML2_LogoutResponse) {
			$signingEnabled = $srcMetadata->getBoolean('sign.logout', NULL);
			if ($signingEnabled === NULL) {
				$signingEnabled = $dstMetadata->getBoolean('sign.logout', NULL);
			}
		} elseif ($message instanceof SAML2_AuthnRequest) {
			$signingEnabled = $srcMetadata->getBoolean('sign.authnrequest', NULL);
			if ($signingEnabled === NULL) {
				$signingEnabled = $dstMetadata->getBoolean('sign.authnrequest', NULL);
			}
		}

		if ($signingEnabled === NULL) {
			$signingEnabled = $dstMetadata->getBoolean('redirect.sign', NULL);
			if ($signingEnabled === NULL) {
				$signingEnabled = $srcMetadata->getBoolean('redirect.sign', FALSE);
			}
		}
		if (!$signingEnabled) {
			return;
		}

		self::addSign($srcMetadata, $dstMetadata, $message);
	}


	/**
	 * Find the certificate used to sign a message or assertion.
	 *
	 * An exception is thrown if we are unable to locate the certificate.
	 *
	 * @param array $certFingerprints  The fingerprints we are looking for.
	 * @param array $certificates  Array of certificates.
	 * @return string  Certificate, in PEM-format.
	 */
	private static function findCertificate(array $certFingerprints, array $certificates) {

		$candidates = array();

		foreach ($certificates as $cert) {
			$fp = strtolower(sha1(base64_decode($cert)));
			if (!in_array($fp, $certFingerprints, TRUE)) {
				$candidates[] = $fp;
				continue;
			}

			/* We have found a matching fingerprint. */
			$pem = "-----BEGIN CERTIFICATE-----\n" .
				chunk_split($cert, 64) .
				"-----END CERTIFICATE-----\n";
			return $pem;
		}

		$candidates = "'" . implode("', '", $candidates) . "'";
		$fps = "'" .  implode("', '", $certFingerprints) . "'";
		throw new SimpleSAML_Error_Exception('Unable to find a certificate matching the configured ' .
			'fingerprint. Candidates: ' . $candidates . '; certFingerprint: ' . $fps . '.');
	}


	/**
	 * Check the signature on a SAML2 message or assertion.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender.
	 * @param SAML2_SignedElement $element  Either a SAML2_Response or a SAML2_Assertion.
	 */
	public static function checkSign(SimpleSAML_Configuration $srcMetadata, SAML2_SignedElement $element) {

		/* Find the public key that should verify signatures by this entity. */
		$keys = $srcMetadata->getPublicKeys('signing');
		if ($keys !== NULL) {
			$pemKeys = array();
			foreach ($keys as $key) {
				switch ($key['type']) {
				case 'X509Certificate':
					$pemKeys[] = "-----BEGIN CERTIFICATE-----\n" .
						chunk_split($key['X509Certificate'], 64) .
						"-----END CERTIFICATE-----\n";
					break;
				default:
					SimpleSAML_Logger::debug('Skipping unknown key type: ' . $key['type']);
				}
			}

		} elseif ($srcMetadata->hasValue('certFingerprint')) {
			$certFingerprint = $srcMetadata->getArrayizeString('certFingerprint');
			foreach ($certFingerprint as &$fp) {
				$fp = strtolower(str_replace(':', '', $fp));
			}

			$certificates = $element->getCertificates();

			/*
			 * We don't have the full certificate stored. Try to find it
			 * in the message or the assertion instead.
			 */
			if (count($certificates) === 0) {
				/* We need the full certificate in order to match it against the fingerprint. */
				SimpleSAML_Logger::debug('No certificate in message when validating against fingerprint.');
				return FALSE;
			} else {
				SimpleSAML_Logger::debug('Found ' . count($certificates) . ' certificates in ' . get_class($element));
			}

			$pemCert = self::findCertificate($certFingerprint, $certificates);
			$pemKeys = array($pemCert);
		} else {
			throw new SimpleSAML_Error_Exception(
				'Missing certificate in metadata for ' .
				var_export($srcMetadata->getString('entityid'), TRUE));
		}

		SimpleSAML_Logger::debug('Has ' . count($pemKeys) . ' candidate keys for validation.');

		$lastException = NULL;
		foreach ($pemKeys as $i => $pem) {
			$key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'public'));
			$key->loadKey($pem);

			try {
				/*
				 * Make sure that we have a valid signature on either the response
				 * or the assertion.
				 */
				$res = $element->validate($key);
				if ($res) {
					SimpleSAML_Logger::debug('Validation with key #' . $i . ' succeeded.');
					return TRUE;
				}
				SimpleSAML_Logger::debug('Validation with key #' . $i . ' failed without exception.');
			} catch (Exception $e) {
				SimpleSAML_Logger::debug('Validation with key #' . $i . ' failed with exception: ' . $e->getMessage());
				$lastException = $e;
			}
		}

		/* We were unable to validate the signature with any of our keys. */
		if ($lastException !== NULL) {
			throw $lastException;
		} else {
			return FALSE;
		}
	}


	/**
	 * Check signature on a SAML2 message if enabled.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender.
	 * @param SimpleSAML_Configuration $dstMetadata  The metadata of the recipient.
	 * @param SAML2_Message $message  The message we should check the signature on.
	 */
	public static function validateMessage(
		SimpleSAML_Configuration $srcMetadata,
		SimpleSAML_Configuration $dstMetadata,
		SAML2_Message $message
		) {

		if ($message instanceof SAML2_LogoutRequest || $message instanceof SAML2_LogoutResponse) {
			$enabled = $srcMetadata->getBoolean('validate.logout', NULL);
			if ($enabled === NULL) {
				$enabled = $dstMetadata->getBoolean('validate.logout', NULL);
			}
		} elseif ($message instanceof SAML2_AuthnRequest) {
			$enabled = $srcMetadata->getBoolean('validate.authnrequest', NULL);
			if ($enabled === NULL) {
				$enabled = $dstMetadata->getBoolean('validate.authnrequest', NULL);
			}
		}

		if ($enabled === NULL) {
			$enabled = $srcMetadata->getBoolean('redirect.validate', NULL);
			if ($enabled === NULL) {
				$enabled = $dstMetadata->getBoolean('redirect.validate', FALSE);
			}
		}

		if (!$enabled) {
			return;
		}

		if (!self::checkSign($srcMetadata, $message)) {
			throw new SimpleSAML_Error_Exception('Validation of received messages enabled, but no signature found on message.');
		}
	}


	/**
	 * Retrieve the decryption keys from metadata.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender (IdP).
	 * @param SimpleSAML_Configuration $dstMetadata  The metadata of the recipient (SP).
	 * @return array  Array of decryption keys.
	 */
	public static function getDecryptionKeys(SimpleSAML_Configuration $srcMetadata,
		SimpleSAML_Configuration $dstMetadata) {

		$sharedKey = $srcMetadata->getString('sharedkey', NULL);
		if ($sharedKey !== NULL) {
			$key = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
			$key->loadKey($sharedKey);
			return array($key);
		}

		$keys = array();

		/* Load the new private key if it exists. */
		$keyArray = SimpleSAML\Utils\Crypto::loadPrivateKey($dstMetadata, FALSE, 'new_');
		if ($keyArray !== NULL) {
			assert('isset($keyArray["PEM"])');

			$key = new XMLSecurityKey(XMLSecurityKey::RSA_1_5, array('type'=>'private'));
			if (array_key_exists('password', $keyArray)) {
				$key->passphrase = $keyArray['password'];
			}
			$key->loadKey($keyArray['PEM']);
			$keys[] = $key;
		}

		/* Find the existing private key. */
		$keyArray = SimpleSAML\Utils\Crypto::loadPrivateKey($dstMetadata, TRUE);
		assert('isset($keyArray["PEM"])');

		$key = new XMLSecurityKey(XMLSecurityKey::RSA_1_5, array('type'=>'private'));
		if (array_key_exists('password', $keyArray)) {
			$key->passphrase = $keyArray['password'];
		}
		$key->loadKey($keyArray['PEM']);
		$keys[] = $key;

		return $keys;
	}


	/**
	 * Retrieve blacklisted algorithms.
	 *
	 * Remote configuration overrides local configuration.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender.
	 * @param SimpleSAML_Configuration $dstMetadata  The metadata of the recipient.
	 * @return array  Array of blacklisted algorithms.
	 */
	public static function getBlacklistedAlgorithms(SimpleSAML_Configuration $srcMetadata,
		SimpleSAML_Configuration $dstMetadata) {

		$blacklist = $srcMetadata->getArray('encryption.blacklisted-algorithms', NULL);
		if ($blacklist === NULL) {
			$blacklist = $dstMetadata->getArray('encryption.blacklisted-algorithms', array(XMLSecurityKey::RSA_1_5));
		}
		return $blacklist;
	}


	/**
	 * Decrypt an assertion.
	 *
	 * This function takes in a SAML2_Assertion and decrypts it if it is encrypted.
	 * If it is unencrypted, and encryption is enabled in the metadata, an exception
	 * will be throws.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender (IdP).
	 * @param SimpleSAML_Configuration $dstMetadata  The metadata of the recipient (SP).
	 * @param SAML2_Assertion|SAML2_EncryptedAssertion $assertion  The assertion we are decrypting.
	 * @return SAML2_Assertion  The assertion.
	 */
	private static function decryptAssertion(SimpleSAML_Configuration $srcMetadata,
		SimpleSAML_Configuration $dstMetadata, $assertion) {
		assert('$assertion instanceof SAML2_Assertion || $assertion instanceof SAML2_EncryptedAssertion');

		if ($assertion instanceof SAML2_Assertion) {
			$encryptAssertion = $srcMetadata->getBoolean('assertion.encryption', NULL);
			if ($encryptAssertion === NULL) {
				$encryptAssertion = $dstMetadata->getBoolean('assertion.encryption', FALSE);
			}
			if ($encryptAssertion) {
				/* The assertion was unencrypted, but we have encryption enabled. */
				throw new Exception('Received unencrypted assertion, but encryption was enabled.');
			}

			return $assertion;
		}

		try {
			$keys = self::getDecryptionKeys($srcMetadata, $dstMetadata);
		} catch (Exception $e) {
			throw new SimpleSAML_Error_Exception('Error decrypting assertion: ' . $e->getMessage());
		}

		$blacklist = self::getBlacklistedAlgorithms($srcMetadata, $dstMetadata);

		$lastException = NULL;
		foreach ($keys as $i => $key) {
			try {
				$ret = $assertion->getAssertion($key, $blacklist);
				SimpleSAML_Logger::debug('Decryption with key #' . $i . ' succeeded.');
				return $ret;
			} catch (Exception $e) {
				SimpleSAML_Logger::debug('Decryption with key #' . $i . ' failed with exception: ' . $e->getMessage());
				$lastException = $e;
			}
		}
		throw $lastException;
	}


	/**
	 * Retrieve the status code of a response as a sspmod_saml_Error.
	 *
	 * @param SAML2_StatusResponse $response  The response.
	 * @return sspmod_saml_Error  The error.
	 */
	public static function getResponseError(SAML2_StatusResponse $response) {

		$status = $response->getStatus();
		return new sspmod_saml_Error($status['Code'], $status['SubCode'], $status['Message']);
	}


	/**
	 * Build an authentication request based on information in the metadata.
	 *
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the service provider.
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the identity provider.
	 */
	public static function buildAuthnRequest(SimpleSAML_Configuration $spMetadata, SimpleSAML_Configuration $idpMetadata) {

		$ar = new SAML2_AuthnRequest();

		if ($spMetadata->hasValue('NameIDPolicy')) {
			$nameIdPolicy = $spMetadata->getString('NameIDPolicy', NULL);
		} else {
			$nameIdPolicy = $spMetadata->getString('NameIDFormat', SAML2_Const::NAMEID_TRANSIENT);
		}

		if ($nameIdPolicy !== NULL) {
			$ar->setNameIdPolicy(array(
				'Format' => $nameIdPolicy,
				'AllowCreate' => TRUE,
			));
		}

		$ar->setForceAuthn($spMetadata->getBoolean('ForceAuthn', FALSE));
		$ar->setIsPassive($spMetadata->getBoolean('IsPassive', FALSE));

		$protbind = $spMetadata->getValueValidate('ProtocolBinding', array(
				SAML2_Const::BINDING_HTTP_POST,
				SAML2_Const::BINDING_HOK_SSO,
				SAML2_Const::BINDING_HTTP_ARTIFACT,
				SAML2_Const::BINDING_HTTP_REDIRECT,
			), SAML2_Const::BINDING_HTTP_POST);

		/* Shoaib - setting the appropriate binding based on parameter in sp-metadata defaults to HTTP_POST */
		$ar->setProtocolBinding($protbind);

		$ar->setIssuer($spMetadata->getString('entityid'));

		$ar->setAssertionConsumerServiceIndex($spMetadata->getInteger('AssertionConsumerServiceIndex', NULL));
		$ar->setAttributeConsumingServiceIndex($spMetadata->getInteger('AttributeConsumingServiceIndex', NULL));

		if ($spMetadata->hasValue('AuthnContextClassRef')) {
			$accr = $spMetadata->getArrayizeString('AuthnContextClassRef');
			$ar->setRequestedAuthnContext(array('AuthnContextClassRef' => $accr));
		}

		self::addRedirectSign($spMetadata, $idpMetadata, $ar);

		return $ar;
	}


	/**
	 * Build a logout request based on information in the metadata.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender.
	 * @param SimpleSAML_Configuration $dstpMetadata  The metadata of the recipient.
	 */
	public static function buildLogoutRequest(SimpleSAML_Configuration $srcMetadata, SimpleSAML_Configuration $dstMetadata) {

		$lr = new SAML2_LogoutRequest();
		$lr->setIssuer($srcMetadata->getString('entityid'));

		self::addRedirectSign($srcMetadata, $dstMetadata, $lr);

		return $lr;
	}


	/**
	 * Build a logout response based on information in the metadata.
	 *
	 * @param SimpleSAML_Configuration $srcMetadata  The metadata of the sender.
	 * @param SimpleSAML_Configuration $dstpMetadata  The metadata of the recipient.
	 */
	public static function buildLogoutResponse(SimpleSAML_Configuration $srcMetadata, SimpleSAML_Configuration $dstMetadata) {

		$lr = new SAML2_LogoutResponse();
		$lr->setIssuer($srcMetadata->getString('entityid'));

		self::addRedirectSign($srcMetadata, $dstMetadata, $lr);

		return $lr;
	}


	/**
	 * Process a response message.
	 *
	 * If the response is an error response, we will throw a sspmod_saml_Error
	 * exception with the error.
	 *
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the service provider.
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the identity provider.
	 * @param SAML2_Response $response  The response.
	 * @return array  Array with SAML2_Assertion objects, containing valid assertions from the response.
	 */
	public static function processResponse(
		SimpleSAML_Configuration $spMetadata, SimpleSAML_Configuration $idpMetadata,
		SAML2_Response $response
		) {

		if (!$response->isSuccess()) {
			throw self::getResponseError($response);
		}

		/* Validate Response-element destination. */
		$currentURL = \SimpleSAML\Utils\HTTP::getSelfURLNoQuery();
		$msgDestination = $response->getDestination();
		if ($msgDestination !== NULL && $msgDestination !== $currentURL) {
			throw new Exception('Destination in response doesn\'t match the current URL. Destination is "' .
				$msgDestination . '", current URL is "' . $currentURL . '".');
		}

		$responseSigned = self::checkSign($idpMetadata, $response);

		/*
		 * When we get this far, the response itself is valid.
		 * We only need to check signatures and conditions of the response.
		 */

		$assertion = $response->getAssertions();
		if (empty($assertion)) {
			throw new SimpleSAML_Error_Exception('No assertions found in response from IdP.');
		}

		$ret = array();
		foreach ($assertion as $a) {
			$ret[] = self::processAssertion($spMetadata, $idpMetadata, $response, $a, $responseSigned);
		}

		return $ret;
	}


	/**
	 * Process an assertion in a response.
	 *
	 * Will throw an exception if it is invalid.
	 *
	 * @param SimpleSAML_Configuration $spMetadata  The metadata of the service provider.
	 * @param SimpleSAML_Configuration $idpMetadata  The metadata of the identity provider.
	 * @param SAML2_Response $response  The response containing the assertion.
	 * @param SAML2_Assertion|SAML2_EncryptedAssertion $assertion  The assertion.
	 * @param bool $responseSigned  Whether the response is signed.
	 * @return SAML2_Assertion  The assertion, if it is valid.
	 */
	private static function processAssertion(
		SimpleSAML_Configuration $spMetadata, SimpleSAML_Configuration $idpMetadata,
		SAML2_Response $response, $assertion, $responseSigned
		) {
		assert('$assertion instanceof SAML2_Assertion || $assertion instanceof SAML2_EncryptedAssertion');
		assert('is_bool($responseSigned)');

		$assertion = self::decryptAssertion($idpMetadata, $spMetadata, $assertion);

		if (!self::checkSign($idpMetadata, $assertion)) {
			if (!$responseSigned) {
				throw new SimpleSAML_Error_Exception('Neither the assertion nor the response was signed.');
			}
		}
		/* At least one valid signature found. */

		$currentURL = \SimpleSAML\Utils\HTTP::getSelfURLNoQuery();


		/* Check various properties of the assertion. */

		$notBefore = $assertion->getNotBefore();
		if ($notBefore !== NULL && $notBefore > time() + 60) {
			throw new SimpleSAML_Error_Exception('Received an assertion that is valid in the future. Check clock synchronization on IdP and SP.');
		}

		$notOnOrAfter = $assertion->getNotOnOrAfter();
		if ($notOnOrAfter !== NULL && $notOnOrAfter <= time() - 60) {
			throw new SimpleSAML_Error_Exception('Received an assertion that has expired. Check clock synchronization on IdP and SP.');
		}

		$sessionNotOnOrAfter = $assertion->getSessionNotOnOrAfter();
		if ($sessionNotOnOrAfter !== NULL && $sessionNotOnOrAfter <= time() - 60) {
			throw new SimpleSAML_Error_Exception('Received an assertion with a session that has expired. Check clock synchronization on IdP and SP.');
		}

		$validAudiences = $assertion->getValidAudiences();
		if ($validAudiences !== NULL) {
			$spEntityId = $spMetadata->getString('entityid');
			if (!in_array($spEntityId, $validAudiences, TRUE)) {
				$candidates = '[' . implode('], [', $validAudiences) . ']';
				throw new SimpleSAML_Error_Exception('This SP [' . $spEntityId . ']  is not a valid audience for the assertion. Candidates were: ' . $candidates);
			}
		}

		$found = FALSE;
		$lastError = 'No SubjectConfirmation element in Subject.';
		$validSCMethods = array(SAML2_Const::CM_BEARER, SAML2_Const::CM_HOK, SAML2_Const::CM_VOUCHES);
		foreach ($assertion->getSubjectConfirmation() as $sc) {
			if (!in_array($sc->Method, $validSCMethods)) {
				$lastError = 'Invalid Method on SubjectConfirmation: ' . var_export($sc->Method, TRUE);
				continue;
			}

			/* Is SSO with HoK enabled? IdP remote metadata overwrites SP metadata configuration. */
			$hok = $idpMetadata->getBoolean('saml20.hok.assertion', NULL);
			if ($hok === NULL) {
				$hok = $spMetadata->getBoolean('saml20.hok.assertion', FALSE);
			}
			if ($sc->Method === SAML2_Const::CM_BEARER && $hok) {
				$lastError = 'Bearer SubjectConfirmation received, but Holder-of-Key SubjectConfirmation needed';
				continue;
			}
			if ($sc->Method === SAML2_Const::CM_HOK && !$hok) {
				$lastError = 'Holder-of-Key SubjectConfirmation received, but the Holder-of-Key profile is not enabled.';
				continue;
			}

			$scd = $sc->SubjectConfirmationData;
			if ($sc->Method === SAML2_Const::CM_HOK) {
				/* Check HoK Assertion */
				if (\SimpleSAML\Utils\HTTP::isHTTPS() === FALSE) {
					$lastError = 'No HTTPS connection, but required for Holder-of-Key SSO';
					continue;
				}
				if (isset($_SERVER['SSL_CLIENT_CERT']) && empty($_SERVER['SSL_CLIENT_CERT'])) {
					$lastError = 'No client certificate provided during TLS Handshake with SP';
					continue;
				}
				/* Extract certificate data (if this is a certificate). */
				$clientCert = $_SERVER['SSL_CLIENT_CERT'];
				$pattern = '/^-----BEGIN CERTIFICATE-----([^-]*)^-----END CERTIFICATE-----/m';
				if (!preg_match($pattern, $clientCert, $matches)) {
					$lastError = 'Error while looking for client certificate during TLS handshake with SP, the client certificate does not '
								. 'have the expected structure';
					continue;
				}
				/* We have a valid client certificate from the browser. */
				$clientCert = str_replace(array("\r", "\n", " "), '', $matches[1]);

				foreach ($scd->info as $thing) {
					if($thing instanceof SAML2_XML_ds_KeyInfo) {
						$keyInfo[]=$thing;
					}
				}
				if (count($keyInfo)!=1) {
					$lastError = 'Error validating Holder-of-Key assertion: Only one <ds:KeyInfo> element in <SubjectConfirmationData> allowed';
					continue;
				}

				foreach ($keyInfo[0]->info as $thing) {
					if($thing instanceof SAML2_XML_ds_X509Data) {
						$x509data[]=$thing;
					}
				}
				if (count($x509data)!=1) {
					$lastError = 'Error validating Holder-of-Key assertion: Only one <ds:X509Data> element in <ds:KeyInfo> within <SubjectConfirmationData> allowed';
					continue;
				}

				foreach ($x509data[0]->data as $thing) {
					if($thing instanceof SAML2_XML_ds_X509Certificate) {
						$x509cert[]=$thing;
					}
				}
				if (count($x509cert)!=1) {
					$lastError = 'Error validating Holder-of-Key assertion: Only one <ds:X509Certificate> element in <ds:X509Data> within <SubjectConfirmationData> allowed';
					continue;
				}

				$HoKCertificate = $x509cert[0]->certificate;
				if ($HoKCertificate !== $clientCert) {
					$lastError = 'Provided client certificate does not match the certificate bound to the Holder-of-Key assertion';
					continue;
				}
			}

			// if no SubjectConfirmationData then don't do anything.
			if ($scd === null) {
				$lastError = 'No SubjectConfirmationData provided';
				continue;
			}

			if ($scd->NotBefore && $scd->NotBefore > time() + 60) {
				$lastError = 'NotBefore in SubjectConfirmationData is in the future: ' . $scd->NotBefore;
				continue;
			}
			if ($scd->NotOnOrAfter && $scd->NotOnOrAfter <= time() - 60) {
				$lastError = 'NotOnOrAfter in SubjectConfirmationData is in the past: ' . $scd->NotOnOrAfter;
				continue;
			}
			if ($scd->Recipient !== NULL && $scd->Recipient !== $currentURL) {
				$lastError = 'Recipient in SubjectConfirmationData does not match the current URL. Recipient is ' .
					var_export($scd->Recipient, TRUE) . ', current URL is ' . var_export($currentURL, TRUE) . '.';
				continue;
			}
			if ($scd->InResponseTo !== NULL && $response->getInResponseTo() !== NULL && $scd->InResponseTo !== $response->getInResponseTo()) {
				$lastError = 'InResponseTo in SubjectConfirmationData does not match the Response. Response has ' .
					var_export($response->getInResponseTo(), TRUE) . ', SubjectConfirmationData has ' . var_export($scd->InResponseTo, TRUE) . '.';
				continue;
			}
			$found = TRUE;
			break;
		}
		if (!$found) {
			throw new SimpleSAML_Error_Exception('Error validating SubjectConfirmation in Assertion: ' . $lastError);
		}

		/* As far as we can tell, the assertion is valid. */


		/* Maybe we need to base64 decode the attributes in the assertion? */
		if ($idpMetadata->getBoolean('base64attributes', FALSE)) {
			$attributes = $assertion->getAttributes();
			$newAttributes = array();
			foreach ($attributes as $name => $values) {
				$newAttributes[$name] = array();
				foreach ($values as $value) {
					foreach(explode('_', $value) AS $v) {
						$newAttributes[$name][] = base64_decode($v);
					}
				}
			}
			$assertion->setAttributes($newAttributes);
		}


		/* Decrypt the NameID element if it is encrypted. */
		if ($assertion->isNameIdEncrypted()) {
			try {
				$keys = self::getDecryptionKeys($idpMetadata, $spMetadata);
			} catch (Exception $e) {
				throw new SimpleSAML_Error_Exception('Error decrypting NameID: ' . $e->getMessage());
			}

			$blacklist = self::getBlacklistedAlgorithms($idpMetadata, $spMetadata);

			$lastException = NULL;
			foreach ($keys as $i => $key) {
				try {
					$assertion->decryptNameId($key, $blacklist);
					SimpleSAML_Logger::debug('Decryption with key #' . $i . ' succeeded.');
					$lastException = NULL;
					break;
				} catch (Exception $e) {
					SimpleSAML_Logger::debug('Decryption with key #' . $i . ' failed with exception: ' . $e->getMessage());
					$lastException = $e;
				}
			}
			if ($lastException !== NULL) {
				throw $lastException;
			}
		}

		return $assertion;
	}


	/**
	 * Retrieve the encryption key for the given entity.
	 *
	 * @param SimpleSAML_Configuration $metadata  The metadata of the entity.
	 * @return XMLSecurityKey  The encryption key.
	 */
	public static function getEncryptionKey(SimpleSAML_Configuration $metadata) {

		$sharedKey = $metadata->getString('sharedkey', NULL);
		if ($sharedKey !== NULL) {
			$key = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
			$key->loadKey($sharedKey);
			return $key;
		}

		$keys = $metadata->getPublicKeys('encryption', TRUE);
		foreach ($keys as $key) {
			switch ($key['type']) {
			case 'X509Certificate':
				$pemKey = "-----BEGIN CERTIFICATE-----\n" .
					chunk_split($key['X509Certificate'], 64) .
					"-----END CERTIFICATE-----\n";
				$key = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type'=>'public'));
				$key->loadKey($pemKey);
				return $key;
			}
		}

		throw new SimpleSAML_Error_Exception('No supported encryption key in ' . var_export($metadata->getString('entityid'), TRUE));
	}

}
