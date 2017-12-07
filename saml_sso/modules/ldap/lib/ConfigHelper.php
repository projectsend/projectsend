<?php

/**
 * LDAP authentication source configuration parser.
 *
 * See the ldap-entry in config-templates/authsources.php for information about
 * configuration of these options.
 *
 * @package SimpleSAMLphp
 */
class sspmod_ldap_ConfigHelper {


	/**
	 * String with the location of this configuration.
	 * Used for error reporting.
	 */
	private $location;


	/**
	 * The hostname of the LDAP server.
	 */
	private $hostname;


	/**
	 * Whether we should use TLS/SSL when contacting the LDAP server.
	 */
	private $enableTLS;


	/**
	 * Whether debug output is enabled.
	 *
	 * @var bool
	 */
	private $debug;


	/**
	 * The timeout for accessing the LDAP server.
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * The port used when accessing the LDAP server.
	 *
	 * @var int
	 */
	private $port;

	/**
	 * Whether to follow referrals
	 */
	private $referrals;


	/**
	 * Whether we need to search for the users DN.
	 */
	private $searchEnable;


	/**
	 * The username we should bind with before we can search for the user.
	 */
	private $searchUsername;


	/**
	 * The password we should bind with before we can search for the user.
	 */
	private $searchPassword;


	/**
	 * Array with the base DN(s) for the search.
	 */
	private $searchBase;


	/**
	 * The attributes which should match the username.
	 */
	private $searchAttributes;


	/**
	 * The DN pattern we should use to create the DN from the username.
	 */
	private $dnPattern;


	/**
	 * The attributes we should fetch. Can be NULL in which case we will fetch all attributes.
	 */
	private $attributes;


	/**
	 * The user cannot get all attributes, privileged reader required
	 */
	private $privRead;


	/**
	 * The DN we should bind with before we can get the attributes.
	 */
	private $privUsername;


	/**
	 * The password we should bind with before we can get the attributes.
	 */
	private $privPassword;


	/**
	 * Constructor for this configuration parser.
	 *
	 * @param array $config  Configuration.
	 * @param string $location  The location of this configuration. Used for error reporting.
	 */
	public function __construct($config, $location) {
		assert('is_array($config)');
		assert('is_string($location)');

		$this->location = $location;

		// Parse configuration
		$config = SimpleSAML_Configuration::loadFromArray($config, $location);

		$this->hostname = $config->getString('hostname');
		$this->enableTLS = $config->getBoolean('enable_tls', FALSE);
		$this->debug = $config->getBoolean('debug', FALSE);
		$this->timeout = $config->getInteger('timeout', 0);
		$this->port = $config->getInteger('port', 389);
		$this->referrals = $config->getBoolean('referrals', TRUE);
		$this->searchEnable = $config->getBoolean('search.enable', FALSE);
		$this->privRead = $config->getBoolean('priv.read', FALSE);

		if ($this->searchEnable) {
			$this->searchUsername = $config->getString('search.username', NULL);
			if ($this->searchUsername !== NULL) {
				$this->searchPassword = $config->getString('search.password');
			}

			$this->searchBase = $config->getArrayizeString('search.base');
			$this->searchAttributes = $config->getArray('search.attributes');

		} else {
			$this->dnPattern = $config->getString('dnpattern');
		}

		// Are privs needed to get to the attributes?
		if ($this->privRead) {
			$this->privUsername = $config->getString('priv.username');
			$this->privPassword = $config->getString('priv.password');
		}

		$this->attributes = $config->getArray('attributes', NULL);
	}


	/**
	 * Attempt to log in using the given username and password.
	 *
	 * Will throw a SimpleSAML_Error_Error('WRONGUSERPASS') if the username or password is wrong.
	 * If there is a configuration problem, an Exception will be thrown.
	 *
	 * @param string $username  The username the user wrote.
	 * @param string $password  The password the user wrote.
	 * @param arrray $sasl_args  Array of SASL options for LDAP bind.
	 * @return array  Associative array with the users attributes.
	 */
	public function login($username, $password, array $sasl_args = NULL) {
		assert('is_string($username)');
		assert('is_string($password)');

		if (empty($password)) {
			SimpleSAML_Logger::info($this->location . ': Login with empty password disallowed.');
			throw new SimpleSAML_Error_Error('WRONGUSERPASS');
		}

		$ldap = new SimpleSAML_Auth_LDAP($this->hostname, $this->enableTLS, $this->debug, $this->timeout, $this->port, $this->referrals);

		if (!$this->searchEnable) {
			$ldapusername = addcslashes($username, ',+"\\<>;*');
			$dn = str_replace('%username%', $ldapusername, $this->dnPattern);
		} else {
			if ($this->searchUsername !== NULL) {
				if(!$ldap->bind($this->searchUsername, $this->searchPassword)) {
					throw new Exception('Error authenticating using search username & password.');
				}
			}

			$dn = $ldap->searchfordn($this->searchBase, $this->searchAttributes, $username, TRUE);
			if ($dn === NULL) {
				/* User not found with search. */
				SimpleSAML_Logger::info($this->location . ': Unable to find users DN. username=\'' . $username . '\'');
				throw new SimpleSAML_Error_Error('WRONGUSERPASS');
			}
		}

		if (!$ldap->bind($dn, $password, $sasl_args)) {
			SimpleSAML_Logger::info($this->location . ': '. $username . ' failed to authenticate. DN=' . $dn);
			throw new SimpleSAML_Error_Error('WRONGUSERPASS');
		}

		/* In case of SASL bind, authenticated and authorized DN may differ */
		if (isset($sasl_args))
			$dn = $ldap->whoami($this->searchBase, $this->searchAttributes);

		/* Are privs needed to get the attributes? */
		if ($this->privRead) {
			/* Yes, rebind with privs */
			if(!$ldap->bind($this->privUsername, $this->privPassword)) {
				throw new Exception('Error authenticating using privileged DN & password.');
			}
		}

		return $ldap->getAttributes($dn, $this->attributes);
	}


	/**
	 * Search for a DN.
	 *
	 * @param string|array $attribute
	 * The attribute name(s) searched for. If set to NULL, values from
	 * configuration is used.
	 * @param string $value
	 * The attribute value searched for.
	 * @param bool $allowZeroHits
	 * Determines if the method will throw an exception if no
	 * hits are found. Defaults to FALSE.
	 * @return string
	 * The DN of the matching element, if found. If no element was
	 * found and $allowZeroHits is set to FALSE, an exception will
	 * be thrown; otherwise NULL will be returned.
	 * @throws SimpleSAML_Error_AuthSource if:
	 * - LDAP search encounter some problems when searching cataloge
	 * - Not able to connect to LDAP server
	 * @throws SimpleSAML_Error_UserNotFound if:
	 * - $allowZeroHits er TRUE and no result is found
	 *
	 */
	public function searchfordn($attribute, $value, $allowZeroHits) {
		$ldap = new SimpleSAML_Auth_LDAP($this->hostname,
			$this->enableTLS,
			$this->debug,
			$this->timeout,
			$this->port,
			$this->referrals);

		if ($attribute == NULL)
			$attribute = $this->searchAttributes;

		if ($this->searchUsername !== NULL) {
			if(!$ldap->bind($this->searchUsername, $this->searchPassword)) {
				throw new Exception('Error authenticating using search username & password.');
			}
		}

		return $ldap->searchfordn($this->searchBase, $attribute,
			$value, $allowZeroHits);
	}

	public function getAttributes($dn, $attributes = NULL) {
		if ($attributes == NULL)
			$attributes = $this->attributes;

		$ldap = new SimpleSAML_Auth_LDAP($this->hostname,
			$this->enableTLS,
			$this->debug,
			$this->timeout,
			$this->port,
			$this->referrals);

		/* Are privs needed to get the attributes? */
		if ($this->privRead) {
			/* Yes, rebind with privs */
			if(!$ldap->bind($this->privUsername, $this->privPassword)) {
				throw new Exception('Error authenticating using privileged DN & password.');
			}
		}

		return $ldap->getAttributes($dn, $attributes);
	}

}
