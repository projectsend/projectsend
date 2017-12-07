<?php

/**
 * Filter for setting the SAML 2 common domain cookie.
 *
 * @package SimpleSAMLphp
 */
class sspmod_cdc_Auth_Process_CDC extends SimpleSAML_Auth_ProcessingFilter {


	/**
	 * Our CDC domain.
	 *
	 * @var string
	 */
	private $domain;


	/**
	 * Our CDC client.
	 *
	 * @var sspmod_cdc_Client
	 */
	private $client;


	/**
	 * Initialize this filter.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);
		assert('is_array($config)');

		if (!isset($config['domain'])) {
			throw new SimpleSAML_Error_Exception('Missing domain option in cdc:CDC filter.');
		}
		$this->domain = (string)$config['domain'];

		$this->client = new sspmod_cdc_Client($this->domain);
	}


	/**
	 * Redirect to page setting CDC.
	 *
	 * @param array &$state  The request state.
	 */
	public function process(&$state) {
		assert('is_array($state)');

		if (!isset($state['Source']['entityid'])) {
			SimpleSAML_Logger::warning('saml:CDC: Could not find IdP entityID.');
			return;
		}

		// Save state and build request
		$id = SimpleSAML_Auth_State::saveState($state, 'cdc:resume');

		$returnTo = SimpleSAML_Module::getModuleURL('cdc/resume.php', array('domain' => $this->domain));

		$params = array(
			'id' => $id,
			'entityID' => $state['Source']['entityid'],
		);
		$this->client->sendRequest($returnTo, 'append', $params);
	}

}
