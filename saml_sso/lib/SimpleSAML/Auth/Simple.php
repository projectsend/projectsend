<?php

/**
 * Helper class for simple authentication applications.
 *
 * @package SimpleSAMLphp
 */
class SimpleSAML_Auth_Simple {

	/**
	 * The id of the authentication source we are accessing.
	 *
	 * @var string
	 */
	private $authSource;


	/**
	 * Create an instance with the specified authsource.
	 *
	 * @param string $authSource  The id of the authentication source.
	 */
	public function __construct($authSource) {
		assert('is_string($authSource)');

		$this->authSource = $authSource;
	}


	/**
	 * Retrieve the implementing authentication source.
	 *
	 * @return SimpleSAML_Auth_Source  The authentication source.
	 */
	public function getAuthSource() {
		$as = SimpleSAML_Auth_Source::getById($this->authSource);
		if ($as === null) {
			throw new SimpleSAML_Error_Exception('Invalid authentication source: '.$this->authSource);
		}
		return $as;
	}


	/**
	 * Check if the user is authenticated.
	 *
	 * This function checks if the user is authenticated with the default
	 * authentication source selected by the 'default-authsource' option in
	 * 'config.php'.
	 *
	 * @return bool  TRUE if the user is authenticated, FALSE if not.
	 */
	public function isAuthenticated() {
		$session = SimpleSAML_Session::getSessionFromRequest();

		return $session->isValid($this->authSource);
	}


	/**
	 * Require the user to be authenticated.
	 *
	 * If the user is authenticated, this function returns immediately.
	 *
	 * If the user isn't authenticated, this function will authenticate the
	 * user with the authentication source, and then return the user to the
	 * current page.
	 *
	 * This function accepts an array $params, which controls some parts of
	 * the authentication. See the login()-function for a description.
	 *
	 * @param array $params  Various options to the authentication request.
	 */
	public function requireAuth(array $params = array()) {

		$session = SimpleSAML_Session::getSessionFromRequest();

		if ($session->isValid($this->authSource)) {
			// Already authenticated
			return;
		}

		$this->login($params);
	}


	/**
	 * Start an authentication process.
	 *
	 * This function never returns.
	 *
	 * This function accepts an array $params, which controls some parts of
	 * the authentication. The accepted parameters depends on the authentication
	 * source being used. Some parameters are generic:
	 *  - 'ErrorURL': A URL that should receive errors from the authentication.
	 *  - 'KeepPost': If the current request is a POST request, keep the POST
	 *    data until after the authentication.
	 *  - 'ReturnTo': The URL the user should be returned to after authentication.
	 *  - 'ReturnCallback': The function we should call after the user has
	 *    finished authentication.
	 *
	 * @param array $params  Various options to the authentication request.
	 */
	public function login(array $params = array()) {

		if (array_key_exists('KeepPost', $params)) {
			$keepPost = (bool)$params['KeepPost'];
		} else {
			$keepPost = TRUE;
		}

		if (array_key_exists('ReturnTo', $params)) {
			$returnTo = (string)$params['ReturnTo'];
		} else if (array_key_exists('ReturnCallback', $params)) {
			$returnTo = (array)$params['ReturnCallback'];
		} else {
			$returnTo = \SimpleSAML\Utils\HTTP::getSelfURL();
		}

		if (is_string($returnTo) && $keepPost && $_SERVER['REQUEST_METHOD'] === 'POST') {
			$returnTo = \SimpleSAML\Utils\HTTP::getPOSTRedirectURL($returnTo, $_POST);
		}

		if (array_key_exists('ErrorURL', $params)) {
			$errorURL = (string)$params['ErrorURL'];
		} else {
			$errorURL = NULL;
		}


		if (!isset($params[SimpleSAML_Auth_State::RESTART]) && is_string($returnTo)) {
			/*
			 * A URL to restart the authentication, in case the user bookmarks
			 * something, e.g. the discovery service page.
			 */
			$restartURL = $this->getLoginURL($returnTo);
			$params[SimpleSAML_Auth_State::RESTART] = $restartURL;
		}

		$as = $this->getAuthSource();
		$as->initLogin($returnTo, $errorURL, $params);
		assert('FALSE');
	}


	/**
	 * Log the user out.
	 *
	 * This function logs the user out. It will never return. By default,
	 * it will cause a redirect to the current page after logging the user
	 * out, but a different URL can be given with the $params parameter.
	 *
	 * Generic parameters are:
	 *  - 'ReturnTo': The URL the user should be returned to after logout.
	 *  - 'ReturnCallback': The function that should be called after logout.
	 *  - 'ReturnStateParam': The parameter we should return the state in when redirecting.
	 *  - 'ReturnStateStage': The stage the state array should be saved with.
	 *
	 * @param string|array|NULL $params  Either the URL the user should be redirected to after logging out,
	 *                                   or an array with parameters for the logout. If this parameter is
	 *                                   NULL, we will return to the current page.
	 */
	public function logout($params = NULL) {
		assert('is_array($params) || is_string($params) || is_null($params)');

		if ($params === NULL) {
			$params = \SimpleSAML\Utils\HTTP::getSelfURL();
		}

		if (is_string($params)) {
			$params = array(
				'ReturnTo' => $params,
			);
		}

		assert('is_array($params)');
		assert('isset($params["ReturnTo"]) || isset($params["ReturnCallback"])');

		if (isset($params['ReturnStateParam']) || isset($params['ReturnStateStage'])) {
			assert('isset($params["ReturnStateParam"]) && isset($params["ReturnStateStage"])');
		}

		$session = SimpleSAML_Session::getSessionFromRequest();
		if ($session->isValid($this->authSource)) {
			$state = $session->getAuthData($this->authSource, 'LogoutState');
			if ($state !== NULL) {
				$params = array_merge($state, $params);
			}

			$session->doLogout($this->authSource);

			$params['LogoutCompletedHandler'] = array(get_class(), 'logoutCompleted');

			$as = SimpleSAML_Auth_Source::getById($this->authSource);
			if ($as !== NULL) {
				$as->logout($params);
			}
		}

		self::logoutCompleted($params);
	}


	/**
	 * Called when logout operation completes.
	 *
	 * This function never returns.
	 *
	 * @param array $state  The state after the logout.
	 */
	public static function logoutCompleted($state) {
		assert('is_array($state)');
		assert('isset($state["ReturnTo"]) || isset($state["ReturnCallback"])');

		if (isset($state['ReturnCallback'])) {
			call_user_func($state['ReturnCallback'], $state);
			assert('FALSE');
		} else {
			$params = array();
			if (isset($state['ReturnStateParam']) || isset($state['ReturnStateStage'])) {
				assert('isset($state["ReturnStateParam"]) && isset($state["ReturnStateStage"])');
				$stateID = SimpleSAML_Auth_State::saveState($state, $state['ReturnStateStage']);
				$params[$state['ReturnStateParam']] = $stateID;
			}
			\SimpleSAML\Utils\HTTP::redirectTrustedURL($state['ReturnTo'], $params);
		}
	}


	/**
	 * Retrieve attributes of the current user.
	 *
	 * This function will retrieve the attributes of the current user if
	 * the user is authenticated. If the user isn't authenticated, it will
	 * return an empty array.
	 *
	 * @return array  The users attributes.
	 */
	public function getAttributes() {

		if (!$this->isAuthenticated()) {
			// Not authenticated
			return array();
		}

		// Authenticated
		$session = SimpleSAML_Session::getSessionFromRequest();
		return $session->getAuthData($this->authSource, 'Attributes');
	}


	/**
	 * Retrieve authentication data.
	 *
	 * @param string $name  The name of the parameter, e.g. 'Attributes', 'Expire' or 'saml:sp:IdP'.
	 * @return mixed|NULL  The value of the parameter, or NULL if it isn't found or we are unauthenticated.
	 */
	public function getAuthData($name) {
		assert('is_string($name)');

		if (!$this->isAuthenticated()) {
			return NULL;
		}

		$session = SimpleSAML_Session::getSessionFromRequest();
		return $session->getAuthData($this->authSource, $name);
	}


	/**
	 * Retrieve all authentication data.
	 *
	 * @return array|NULL  All persistent authentication data, or NULL if we aren't authenticated.
	 */
	public function getAuthDataArray() {

		if (!$this->isAuthenticated()) {
			return NULL;
		}

		$session = SimpleSAML_Session::getSessionFromRequest();
		return $session->getAuthState($this->authSource);
	}


	/**
	 * Retrieve a URL that can be used to log the user in.
	 *
	 * @param string|NULL $returnTo The page the user should be returned to afterwards.
	 * If this parameter is NULL, the user will be returned to the current page.
	 * @return string A URL which is suitable for use in link-elements.
	 */
	public function getLoginURL($returnTo = NULL) {
		assert('is_null($returnTo) || is_string($returnTo)');

		if ($returnTo === NULL) {
			$returnTo = \SimpleSAML\Utils\HTTP::getSelfURL();
		}

		$login = SimpleSAML_Module::getModuleURL('core/as_login.php', array(
			'AuthId' => $this->authSource,
			'ReturnTo' => $returnTo,
		));

		return $login;
	}


	/**
	 * Retrieve a URL that can be used to log the user out.
	 *
	 * @param string|NULL $returnTo The page the user should be returned to afterwards.
	 * If this parameter is NULL, the user will be returned to the current page.
	 * @return string A URL which is suitable for use in link-elements.
	 */
	public function getLogoutURL($returnTo = NULL) {
		assert('is_null($returnTo) || is_string($returnTo)');

		if ($returnTo === NULL) {
			$returnTo = \SimpleSAML\Utils\HTTP::getSelfURL();
		}

		$logout = SimpleSAML_Module::getModuleURL('core/as_logout.php', array(
			'AuthId' => $this->authSource,
			'ReturnTo' => $returnTo,
		));

		return $logout;
	}

}
