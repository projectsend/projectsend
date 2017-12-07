<?php

/**
 * Helper class for username/password authentication.
 *
 * This helper class allows for implementations of username/password authentication by
 * implementing a single function: login($username, $password)
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
abstract class sspmod_core_Auth_UserPassBase extends SimpleSAML_Auth_Source {


	/**
	 * The string used to identify our states.
	 */
	const STAGEID = 'sspmod_core_Auth_UserPassBase.state';


	/**
	 * The key of the AuthId field in the state.
	 */
	const AUTHID = 'sspmod_core_Auth_UserPassBase.AuthId';


	/**
	 * Username we should force.
	 *
	 * A forced username cannot be changed by the user.
	 * If this is NULL, we won't force any username.
	 */
	private $forcedUsername;
	
	/**
	 * Links to pages from login page. 
	 * From configuration
	 */
	protected $loginLinks;

	/**
	 * Storage for authsource config option remember.username.enabled
	 * loginuserpass.php and loginuserpassorg.php pages/templates use this option to
	 * present users with a checkbox to save their username for the next login request.
	 * @var bool
	 */
	protected $rememberUsernameEnabled = FALSE;

	/**
	 * Storage for authsource config option remember.username.checked
	 * loginuserpass.php and loginuserpassorg.php pages/templates use this option
	 * to default the remember username checkbox to checked or not.
	 * @var bool
	 */
	protected $rememberUsernameChecked = FALSE;

    /**
     * Storage for general config option session.rememberme.enable.
     * loginuserpass.php page/template uses this option to present
     * users with a checkbox to keep their session alive across
     * different browser sessions (that is, closing and opening the
     * browser again).
     * @var bool
     */
    protected $rememberMeEnabled = FALSE;

    /**
     * Storage for general config option session.rememberme.checked.
     * loginuserpass.php page/template uses this option to default
     * the "remember me" checkbox to checked or not.
     * @var bool
     */
    protected $rememberMeChecked = FALSE;

	/**
	 * Constructor for this authentication source.
	 *
	 * All subclasses who implement their own constructor must call this constructor before
	 * using $config for anything.
	 *
	 * @param array $info  Information about this authentication source.
	 * @param array &$config  Configuration for this authentication source.
	 */
	public function __construct($info, &$config) {
		assert('is_array($info)');
		assert('is_array($config)');
		
		if (isset($config['core:loginpage_links'])) {
			$this->loginLinks = $config['core:loginpage_links'];
		}

		// Call the parent constructor first, as required by the interface
		parent::__construct($info, $config);

		// Get the remember username config options
		if (isset($config['remember.username.enabled'])) {
			$this->rememberUsernameEnabled = (bool) $config['remember.username.enabled'];
			unset($config['remember.username.enabled']);
		}
		if (isset($config['remember.username.checked'])) {
			$this->rememberUsernameChecked = (bool) $config['remember.username.checked'];
			unset($config['remember.username.checked']);
		}

        // get the "remember me" config options
        $sspcnf = SimpleSAML_Configuration::getInstance();
        $this->rememberMeEnabled = $sspcnf->getBoolean('session.rememberme.enable', FALSE);
        $this->rememberMeChecked = $sspcnf->getBoolean('session.rememberme.checked', FALSE);
	}


	/**
	 * Set forced username.
	 *
	 * @param string|NULL $forcedUsername  The forced username.
	 */
	public function setForcedUsername($forcedUsername) {
		assert('is_string($forcedUsername) || is_null($forcedUsername)');
		$this->forcedUsername = $forcedUsername;
	}

	/**
	 * Return login links from configuration
	 */
	public function getLoginLinks() {
		return $this->loginLinks;
	}

	/**
	 * Getter for the authsource config option remember.username.enabled
	 * @return bool
	 */
	public function getRememberUsernameEnabled() {
		return $this->rememberUsernameEnabled;
	}

	/**
	 * Getter for the authsource config option remember.username.checked
	 * @return bool
	 */
	public function getRememberUsernameChecked() {
		return $this->rememberUsernameChecked;
	}

    /**
     * Check if the "remember me" feature is enabled.
     * @return bool TRUE if enabled, FALSE otherwise.
     */
    public function isRememberMeEnabled() {
        return $this->rememberMeEnabled;
    }

    /**
     * Check if the "remember me" checkbox should be checked.
     * @return bool TRUE if enabled, FALSE otherwise.
     */
    public function isRememberMeChecked() {
        return $this->rememberMeChecked;
    }

	/**
	 * Initialize login.
	 *
	 * This function saves the information about the login, and redirects to a
	 * login page.
	 *
	 * @param array &$state  Information about the current authentication.
	 */
	public function authenticate(&$state) {
		assert('is_array($state)');

		/*
		 * Save the identifier of this authentication source, so that we can
		 * retrieve it later. This allows us to call the login()-function on
		 * the current object.
		 */
		$state[self::AUTHID] = $this->authId;

		// What username we should force, if any
		if ($this->forcedUsername !== NULL) {
			/*
			 * This is accessed by the login form, to determine if the user
			 * is allowed to change the username.
			 */
			$state['forcedUsername'] = $this->forcedUsername;
		}

		/* Save the $state-array, so that we can restore it after a redirect. */
		$id = SimpleSAML_Auth_State::saveState($state, self::STAGEID);

		/*
		 * Redirect to the login form. We include the identifier of the saved
		 * state array as a parameter to the login form.
		 */
		$url = SimpleSAML_Module::getModuleURL('core/loginuserpass.php');
		$params = array('AuthState' => $id);
		\SimpleSAML\Utils\HTTP::redirectTrustedURL($url, $params);

		/* The previous function never returns, so this code is never executed. */
		assert('FALSE');
	}


	/**
	 * Attempt to log in using the given username and password.
	 *
	 * On a successful login, this function should return the users attributes. On failure,
	 * it should throw an exception/error. If the error was caused by the user entering the wrong
	 * username or password, a SimpleSAML_Error_Error('WRONGUSERPASS') should be thrown.
	 *
	 * Note that both the username and the password are UTF-8 encoded.
	 *
	 * @param string $username  The username the user wrote.
	 * @param string $password  The password the user wrote.
	 * @return array  Associative array with the user's attributes.
	 */
	abstract protected function login($username, $password);


	/**
	 * Handle login request.
	 *
	 * This function is used by the login form (core/www/loginuserpass.php) when the user
	 * enters a username and password. On success, it will not return. On wrong
	 * username/password failure, and other errors, it will throw an exception.
	 *
	 * @param string $authStateId  The identifier of the authentication state.
	 * @param string $username  The username the user wrote.
	 * @param string $password  The password the user wrote.
	 */
	public static function handleLogin($authStateId, $username, $password) {
		assert('is_string($authStateId)');
		assert('is_string($username)');
		assert('is_string($password)');

		/* Here we retrieve the state array we saved in the authenticate-function. */
		$state = SimpleSAML_Auth_State::loadState($authStateId, self::STAGEID);

		/* Retrieve the authentication source we are executing. */
		assert('array_key_exists(self::AUTHID, $state)');
		$source = SimpleSAML_Auth_Source::getById($state[self::AUTHID]);
		if ($source === NULL) {
			throw new Exception('Could not find authentication source with id ' . $state[self::AUTHID]);
		}

		/*
		 * $source now contains the authentication source on which authenticate()
		 * was called. We should call login() on the same authentication source.
		 */

		/* Attempt to log in. */
		try {
			$attributes = $source->login($username, $password);
		} catch (Exception $e) {
			SimpleSAML_Logger::stats('Unsuccessful login attempt from '.$_SERVER['REMOTE_ADDR'].'.');
			throw $e;
		}

		SimpleSAML_Logger::stats('User \''.$username.'\' has been successfully authenticated.');

		/* Save the attributes we received from the login-function in the $state-array. */
		assert('is_array($attributes)');
		$state['Attributes'] = $attributes;

		/* Return control to SimpleSAMLphp after successful authentication. */
		SimpleSAML_Auth_Source::completeAuth($state);
	}

}
