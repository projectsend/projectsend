<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/oauth/libextinc/OAuth.php');

/**
 * Authenticate using MySpace.
 *
 * @author Brook Schofield, TERENA.
 * @package SimpleSAMLphp
 */
class sspmod_authmyspace_Auth_Source_MySpace extends SimpleSAML_Auth_Source {

	/**
	 * The string used to identify our states.
	 */
	const STAGE_INIT = 'authmyspace:init';

	/**
	 * The key of the AuthId field in the state.
	 */
	const AUTHID = 'authmyspace:AuthId';

	private $key;
	private $secret;


	/**
	 * Constructor for this authentication source.
	 *
	 * @param array $info  Information about this authentication source.
	 * @param array $config  Configuration.
	 */
	public function __construct($info, $config) {
		assert('is_array($info)');
		assert('is_array($config)');

		// Call the parent constructor first, as required by the interface
		parent::__construct($info, $config);

		if (!array_key_exists('key', $config))
			throw new Exception('MySpace authentication source is not properly configured: missing [key]');

		$this->key = $config['key'];

		if (!array_key_exists('secret', $config))
			throw new Exception('MySpace authentication source is not properly configured: missing [secret]');

		$this->secret = $config['secret'];
	}


	/**
	 * Log-in using MySpace platform
	 *
	 * @param array &$state  Information about the current authentication.
	 */
	public function authenticate(&$state) {
		assert('is_array($state)');

		// We are going to need the authId in order to retrieve this authentication source later
		$state[self::AUTHID] = $this->authId;

		$consumer = new sspmod_oauth_Consumer($this->key, $this->secret);

		// Get the request token
		$requestToken = $consumer->getRequestToken('http://api.myspace.com/request_token');
		SimpleSAML_Logger::debug("Got a request token from the OAuth service provider [" .
			$requestToken->key . "] with the secret [" . $requestToken->secret . "]");

		$state['authmyspace:requestToken'] = $requestToken;

		$stateID = SimpleSAML_Auth_State::saveState($state, self::STAGE_INIT);
		SimpleSAML_Logger::debug('authmyspace auth state id = ' . $stateID);

		// Authorize the request token
		$consumer->getAuthorizeRequest('http://api.myspace.com/authorize', $requestToken, TRUE, SimpleSAML_Module::getModuleUrl('authmyspace') . '/linkback.php?stateid=' . $stateID);

	}



	public function finalStep(&$state) {

		$requestToken = $state['authmyspace:requestToken'];

		$consumer = new sspmod_oauth_Consumer($this->key, $this->secret);

		SimpleSAML_Logger::debug("oauth: Using this request token [" .
			$requestToken->key . "] with the secret [" . $requestToken->secret . "]");

		// Replace the request token with an access token
		$accessToken = $consumer->getAccessToken('http://api.myspace.com/access_token', $requestToken);
		SimpleSAML_Logger::debug("Got an access token from the OAuth service provider [" .
			$accessToken->key . "] with the secret [" . $accessToken->secret . "]");

		// People API -  http://developerwiki.myspace.com/index.php?title=People_API
		$userdata = $consumer->getUserInfo('http://api.myspace.com/1.0/people/@me/@self?fields=@all', $accessToken);

		$attributes = array();

		if (is_array($userdata['person'])) {
			foreach($userdata['person'] AS $key => $value) {
				if (is_string($value) || is_int($value))
					$attributes['myspace.' . $key] = array((string)$value);

				if (is_array($value)) {
					foreach($value AS $key2 => $value2) {
						if (is_string($value2) || is_int($value2))
							$attributes['myspace.' . $key . '.' . $key2] = array((string)$value2);
					}
				}
			}

			if (array_key_exists('id', $userdata['person']) ) {

				// person-id in the format of myspace.com.person.1234567890
				if (preg_match('/(\d+)$/',$userdata['person']['id'],$matches)) {
					$attributes['myspace_targetedID'] = array('http://myspace.com!' . $matches[1]);
					$attributes['myspace_uid'] = array($matches[1]);
					$attributes['myspace_user'] = array($matches[1] . '@myspace.com');
				}
			}

			// profileUrl in the format http://www.myspace.com/username
			if (array_key_exists('profileUrl', $userdata['person']) ) {
				if (preg_match('@/([^/]+)$@',$userdata['person']['profileUrl'],$matches)) {
					$attributes['myspace_username'] = array($matches[1]);
					$attributes['myspace_user'] = array($matches[1] . '@myspace.com');
				}
			}
		}

		SimpleSAML_Logger::debug('MySpace Returned Attributes: '. implode(", ",array_keys($attributes)));

		$state['Attributes'] = $attributes;
	}
}
