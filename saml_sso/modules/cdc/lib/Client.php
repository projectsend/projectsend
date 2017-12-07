<?php

/**
 * CDC client class.
 *
 * @package SimpleSAMLphp
 */
class sspmod_cdc_Client {

	/**
	 * Our CDC domain.
	 *
	 * @var string
	 */
	private $domain;


	/**
	 * The CDC server we send requests to.
	 *
	 * @var sspmod_cdc_Server|NULL
	 */
	private $server;


	/**
	 * Initialize a CDC client.
	 *
	 * @param string $domain  The domain we should query the server for.
	 */
	public function __construct($domain) {
		assert('is_string($domain)');

		$this->domain = $domain;
		$this->server = new sspmod_cdc_Server($domain);
	}


	/**
	 * Receive a CDC response.
	 *
	 * @return array|NULL  The response, or NULL if no response is received.
	 */
	public function getResponse() {

		return $this->server->getResponse();
	}


	/**
	 * Send a request.
	 *
	 * @param string $returnTo  The URL we should return to afterwards.
	 * @param string $op  The operation we are performing.
	 * @param array $params  Additional parameters.
	 */
	public function sendRequest($returnTo, $op, array $params = array()) {
		assert('is_string($returnTo)');
		assert('is_string($op)');

		$params['op'] = $op;
		$params['return'] = $returnTo;
		$this->server->sendRequest($params);
	}

}
