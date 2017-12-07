<?php

/**
 * CDC server class.
 *
 * @package SimpleSAMLphp
 */
class sspmod_cdc_Server {

	/**
	 * The domain.
	 *
	 * @var string
	 */
	private $domain;


	/**
	 * The URL to the server.
	 *
	 * @var string
	 */
	private $server;


	/**
	 * Our shared key.
	 *
	 * @var string
	 */
	private $key;


	/**
	 * The lifetime of our cookie, in seconds.
	 *
	 * If this is 0, the cookie will expire when the browser is closed.
	 *
	 * @param int
	 */
	private $cookieLifetime;


	/**
	 * Initialize a CDC server.
	 *
	 * @param string $domain  The domain we are a server for.
	 */
	public function __construct($domain) {
		assert('is_string($domain)');

		$cdcConfig = SimpleSAML_Configuration::getConfig('module_cdc.php');
		$config = $cdcConfig->getConfigItem($domain, NULL);

		if ($config === NULL) {
			throw new SimpleSAML_Error_Exception('Unknown CDC domain: ' . var_export($domain, TRUE));
		}

		$this->domain = $domain;
		$this->server = $config->getString('server');
		$this->key = $config->getString('key');
		$this->cookieLifetime = $config->getInteger('cookie.lifetime', 0);

		if ($this->key === 'ExampleSharedKey') {
			throw new SimpleSAML_Error_Exception('Key for CDC domain ' . var_export($domain, TRUE) . ' not changed from default.');
		}
	}


	/**
	 * Send a request to this CDC server.
	 *
	 * @param array $request  The CDC request.
	 */
	public function sendRequest(array $request) {
		assert('isset($request["return"])');
		assert('isset($request["op"])');

		$request['domain'] = $this->domain;
		$this->send($this->server, 'CDCRequest', $request);
	}


	/**
	 * Parse and validate response received from a CDC server.
	 *
	 * @return array|NULL  The response, or NULL if no response is received.
	 */
	public function getResponse() {

		$response = self::get('CDCResponse');
		if ($response === NULL) {
			return NULL;
		}

		if ($response['domain'] !== $this->domain) {
			throw new SimpleSAML_Error_Exception('Response received from wrong domain.');
		}

		$this->validate('CDCResponse');

		return $response;
	}


	/**
	 * Parse and process a CDC request.
	 */
	public static function processRequest() {
		$request = self::get('CDCRequest');
		if ($request === NULL) {
			throw new SimpleSAML_Error_BadRequest('Missing "CDCRequest" parameter.');
		}

		$domain = $request['domain'];
		$server = new sspmod_cdc_Server($domain);

		$server->validate('CDCRequest');

		$server->handleRequest($request);
	}


	/**
	 * Handle a parsed CDC requst.
	 *
	 * @param array $request
	 */
	private function handleRequest(array $request) {

		if (!isset($request['op'])) {
			throw new SimpleSAML_Error_BadRequest('Missing "op" in CDC request.');
		}
		$op = (string)$request['op'];

		SimpleSAML_Logger::info('Received CDC request with "op": ' . var_export($op, TRUE));

		if (!isset($request['return'])) {
			throw new SimpleSAML_Error_BadRequest('Missing "return" in CDC request.');
		}
		$return = (string)$request['return'];

		switch ($op) {
		case 'append':
			$response = $this->handleAppend($request);
			break;
		case 'delete':
			$response = $this->handleDelete($request);
			break;
		case 'read':
			$response = $this->handleRead($request);
			break;
		default:
			$response = 'unknown-op';
		}

		if (is_string($response)) {
			$response = array(
				'status' => $response,
			);
		}

		$response['op'] = $op;
		if (isset($request['id'])) {
			$response['id'] = (string)$request['id'];
		}
		$response['domain'] = $this->domain;

		$this->send($return, 'CDCResponse', $response);
	}


	/**
	 * Handle an append request.
	 *
	 * @param array $request  The request.
	 * @return array  The response.
	 */
	private function handleAppend(array $request) {

		if (!isset($request['entityID'])) {
			throw new SimpleSAML_Error_BadRequest('Missing entityID in append request.');
		}
		$entityID = (string)$request['entityID'];

		$list = $this->getCDC();

		$prevIndex = array_search($entityID, $list, TRUE);
		if ($prevIndex !== FALSE) {
			unset($list[$prevIndex]);
		}
		$list[] = $entityID;

		$this->setCDC($list);

		return 'ok';
	}


	/**
	 * Handle a delete request.
	 *
	 * @param array $request  The request.
	 * @return array  The response.
	 */
	private function handleDelete(array $request) {
		$params = array(
			'path' => '/',
			'domain' => '.' . $this->domain,
			'secure' => TRUE,
			'httponly' => FALSE,
		);

        \SimpleSAML\Utils\HTTP::setCookie('_saml_idp', NULL, $params, FALSE);
		return 'ok';
	}


	/**
	 * Handle a read request.
	 *
	 * @param array $request  The request.
	 * @return array  The response.
	 */
	private function handleRead(array $request) {

		$list = $this->getCDC();

		return array(
			'status' => 'ok',
			'cdc' => $list,
		);
	}


	/**
	 * Helper function for parsing and validating a CDC message.
	 *
	 * @param string $parameter  The name of the query parameter.
	 * @return array|NULL  The response, or NULL if no response is received.
	 */
	private static function get($parameter) {
		assert('is_string($parameter)');

		if (!isset($_REQUEST[$parameter])) {
			return NULL;
		}
		$message = (string)$_REQUEST[$parameter];

		$message = @base64_decode($message);
		if ($message === FALSE) {
			throw new SimpleSAML_Error_BadRequest('Error base64-decoding CDC message.');
		}

		$message = @json_decode($message, TRUE);
		if ($message === FALSE) {
			throw new SimpleSAML_Error_BadRequest('Error json-decoding CDC message.');
		}

		if (!isset($message['timestamp'])) {
			throw new SimpleSAML_Error_BadRequest('Missing timestamp in CDC message.');
		}
		$timestamp = (int)$message['timestamp'];

		if ($timestamp + 60 < time()) {
			throw new SimpleSAML_Error_BadRequest('CDC signature has expired.');
		}
		if ($timestamp - 60 > time()) {
			throw new SimpleSAML_Error_BadRequest('CDC signature from the future.');
		}

		if (!isset($message['domain'])) {
			throw new SimpleSAML_Error_BadRequest('Missing domain in CDC message.');
		}

		return $message;
	}


	/**
	 * Helper function for validating the signature on a CDC message.
	 *
	 * Will throw an exception if the message is invalid.
	 *
	 * @param string $parameter  The name of the query parameter.
	 */
	private function validate($parameter) {
		assert('is_string($parameter)');
		assert('isset($_REQUEST[$parameter])');

		$message = (string)$_REQUEST[$parameter];

		if (!isset($_REQUEST['Signature'])) {
			throw new SimpleSAML_Error_BadRequest('Missing Signature on CDC message.');
		}
		$signature = (string)$_REQUEST['Signature'];

		$cSignature = $this->calcSignature($message);
		if ($signature !== $cSignature) {
			throw new SimpleSAML_Error_BadRequest('Invalid signature on CDC message.');
		}
	}


	/**
	 * Helper function for sending CDC messages.
	 *
	 * @param string $to  The URL the message should be delivered to.
	 * @param string $parameter  The query parameter the message should be sent in.
	 * @param array $message  The CDC message.
	 */
	private function send($to, $parameter, array $message) {
		assert('is_string($to)');
		assert('is_string($parameter)');

		$message['timestamp'] = time();
		$message = json_encode($message);
		$message = base64_encode($message);

		$signature = $this->calcSignature($message);

		$params = array(
			$parameter => $message,
			'Signature' => $signature,
		);

		$url = \SimpleSAML\Utils\HTTP::addURLParameters($to, $params);
		if (strlen($url) < 2048) {
			\SimpleSAML\Utils\HTTP::redirectTrustedURL($url);
		} else {
			\SimpleSAML\Utils\HTTP::submitPOSTData($to, $params);
		}
	}


	/**
	 * Calculate the signature on the given message.
	 *
	 * @param string $rawMessage  The base64-encoded message.
	 * @return string  The signature.
	 */
	private function calcSignature($rawMessage) {
		assert('is_string($rawMessage)');

		return sha1($this->key . $rawMessage . $this->key);
	}


	/**
	 * Get the IdP entities saved in the common domain cookie.
	 *
	 * @return array  List of IdP entities.
	 */
	private function getCDC() {

		if (!isset($_COOKIE['_saml_idp'])) {
			return array();
		}

		$ret = (string)$_COOKIE['_saml_idp'];
		$ret = explode(' ', $ret);
		foreach ($ret as &$idp) {
			$idp = base64_decode($idp);
			if ($idp === FALSE) {
				// Not properly base64 encoded
				SimpleSAML_Logger::warning('CDC - Invalid base64-encoding of CDC entry.');
				return array();
			}
		}

		return $ret;
	}


	/**
	 * Build a CDC cookie string.
	 *
	 * @param array $list  The list of IdPs.
	 * @return string  The CDC cookie value.
	 */
	function setCDC(array $list) {

		foreach ($list as &$value) {
			$value = base64_encode($value);
		}

		$cookie = implode(' ', $list);

		while (strlen($cookie) > 4000) {
			// The cookie is too long. Remove the oldest elements until it is short enough
			$tmp = explode(' ', $cookie, 2);
			if (count($tmp) === 1) {
				/*
				 * We are left with a single entityID whose base64
				 * representation is too long to fit in a cookie.
				 */
				break;
			}
			$cookie = $tmp[1];
		}

		$params = array(
			'lifetime' => $this->cookieLifetime,
			'path' => '/',
			'domain' => '.' . $this->domain,
			'secure' => TRUE,
			'httponly' => FALSE,
		);

        \SimpleSAML\Utils\HTTP::setCookie('_saml_idp', $cookie, $params, FALSE);
	}

}
