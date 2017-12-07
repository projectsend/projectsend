<?php

/**
 * Authentication source which let the user chooses among a list of
 * other authentication sources
 *
 * @author Lorenzo Gil, Yaco Sistemas S.L.
 * @package SimpleSAMLphp
 */

class sspmod_multiauth_Auth_Source_MultiAuth extends SimpleSAML_Auth_Source {

	/**
	 * The key of the AuthId field in the state.
	 */
	const AUTHID = 'sspmod_multiauth_Auth_Source_MultiAuth.AuthId';

	/**
	 * The string used to identify our states.
	 */
	const STAGEID = 'sspmod_multiauth_Auth_Source_MultiAuth.StageId';

	/**
	 * The key where the sources is saved in the state.
	 */
	const SOURCESID = 'sspmod_multiauth_Auth_Source_MultiAuth.SourceId';

	/**
	 * The key where the selected source is saved in the session.
	 */
	const SESSION_SOURCE = 'multiauth:selectedSource';

	/**
	 * Array of sources we let the user chooses among.
	 */
	private $sources;

	/**
	 * Constructor for this authentication source.
	 *
	 * @param array $info	 Information about this authentication source.
	 * @param array $config	 Configuration.
	 */
	public function __construct($info, $config) {
		assert('is_array($info)');
		assert('is_array($config)');

		// Call the parent constructor first, as required by the interface
		parent::__construct($info, $config);

		if (!array_key_exists('sources', $config)) {
			throw new Exception('The required "sources" config option was not found');
		}

		$globalConfiguration = SimpleSAML_Configuration::getInstance();
		$defaultLanguage = $globalConfiguration->getString('language.default', 'en');
		$authsources = SimpleSAML_Configuration::getConfig('authsources.php');
		$this->sources = array();
		foreach($config['sources'] as $source => $info) {

			if (is_int($source)) { // Backwards compatibility 
				$source = $info;
				$info = array();
			}

			if (array_key_exists('text', $info)) {
				$text = $info['text'];
			} else {
				$text = array($defaultLanguage => $source);
			}

			if (array_key_exists('css-class', $info)) {
				$css_class = $info['css-class'];
			} else {
				// Use the authtype as the css class
				$authconfig = $authsources->getArray($source, NULL);
				if (!array_key_exists(0, $authconfig) || !is_string($authconfig[0])) {
					$css_class = "";
				} else {
					$css_class = str_replace(":", "-", $authconfig[0]);
				}
			}

			$this->sources[] = array(
				'source' => $source,
				'text' => $text,
				'css_class' => $css_class,
			);
		}
	}

	/**
	 * Prompt the user with a list of authentication sources.
	 *
	 * This method saves the information about the configured sources,
	 * and redirects to a page where the user must select one of these
	 * authentication sources.
	 *
	 * This method never return. The authentication process is finished
	 * in the delegateAuthentication method.
	 *
	 * @param array &$state	 Information about the current authentication.
	 */
	public function authenticate(&$state) {
		assert('is_array($state)');

		$state[self::AUTHID] = $this->authId;
		$state[self::SOURCESID] = $this->sources;

		/* Save the $state array, so that we can restore if after a redirect */
		$id = SimpleSAML_Auth_State::saveState($state, self::STAGEID);

		/* Redirect to the select source page. We include the identifier of the
		saved state array as a parameter to the login form */
		$url = SimpleSAML_Module::getModuleURL('multiauth/selectsource.php');
		$params = array('AuthState' => $id);

		// Allowes the user to specify the auth souce to be used
		if(isset($_GET['source'])) {
			$params['source'] = $_GET['source'];
		}

		\SimpleSAML\Utils\HTTP::redirectTrustedURL($url, $params);

		/* The previous function never returns, so this code is never
		executed */
		assert('FALSE');
	}

	/**
	 * Delegate authentication.
	 *
	 * This method is called once the user has choosen one authentication
	 * source. It saves the selected authentication source in the session
	 * to be able to logout properly. Then it calls the authenticate method
	 * on such selected authentication source.
	 *
	 * @param string $authId	Selected authentication source
	 * @param array	 $state	 Information about the current authentication.
	 */
	public static function delegateAuthentication($authId, $state) {
		assert('is_string($authId)');
		assert('is_array($state)');

		$as = SimpleSAML_Auth_Source::getById($authId);
		$valid_sources = array_map(
			function($src) {
				return $src['source'];
			},
			$state[self::SOURCESID]
        );
		if ($as === NULL || !in_array($authId, $valid_sources)) {
			throw new Exception('Invalid authentication source: ' . $authId);
		}

		/* Save the selected authentication source for the logout process. */
		$session = SimpleSAML_Session::getSessionFromRequest();
		$session->setData(self::SESSION_SOURCE, $state[self::AUTHID], $authId);

		try {
			$as->authenticate($state);
		} catch (SimpleSAML_Error_Exception $e) {
			SimpleSAML_Auth_State::throwException($state, $e);
		} catch (Exception $e) {
			$e = new SimpleSAML_Error_UnserializableException($e);
			SimpleSAML_Auth_State::throwException($state, $e);
		}
		SimpleSAML_Auth_Source::completeAuth($state);
	}

	/**
	 * Log out from this authentication source.
	 *
	 * This method retrieves the authentication source used for this
	 * session and then call the logout method on it.
	 *
	 * @param array &$state	 Information about the current logout operation.
	 */
	public function logout(&$state) {
		assert('is_array($state)');

		/* Get the source that was used to authenticate */
		$session = SimpleSAML_Session::getSessionFromRequest();
		$authId = $session->getData(self::SESSION_SOURCE, $this->authId);

		$source = SimpleSAML_Auth_Source::getById($authId);
		if ($source === NULL) {
			throw new Exception('Invalid authentication source during logout: ' . $source);
		}
		/* Then, do the logout on it */
		$source->logout($state);
	}

	/**
	* Set the previous authentication source.
	*
	* This method remembers the authentication source that the user selected
	* by storing its name in a cookie.
	*
	* @param string $source Name of the authentication source the user selected.
	*/
	public function setPreviousSource($source) {
		assert('is_string($source)');

		$cookieName = 'multiauth_source_' . $this->authId;

		$config = SimpleSAML_Configuration::getInstance();
		$params = array(
			/* We save the cookies for 90 days. */
			'lifetime' => (60*60*24*90),
			/* The base path for cookies.
			This should be the installation directory for SimpleSAMLphp. */
			'path' => ('/' . $config->getBaseUrl()),
			'httponly' => FALSE,
		);

        \SimpleSAML\Utils\HTTP::setCookie($cookieName, $source, $params, FALSE);
	}

	/**
	* Get the previous authentication source.
	*
	* This method retrieves the authentication source that the user selected
	* last time or NULL if this is the first time or remembering is disabled.
	*/
	public function getPreviousSource() {
		$cookieName = 'multiauth_source_' . $this->authId;
		if(array_key_exists($cookieName, $_COOKIE)) {
			return $_COOKIE[$cookieName];
		} else {
			return NULL;
		}
	}
}
