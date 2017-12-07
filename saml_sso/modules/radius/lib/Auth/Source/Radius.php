<?php

/**
 * RADIUS authentication source.
 *
 * This class is based on www/auth/login-radius.php.
 *
 * @package SimpleSAMLphp
 */
class sspmod_radius_Auth_Source_Radius extends sspmod_core_Auth_UserPassBase {

	/**
	 * The list of radius servers to use.
	 */
	private $servers;

	/**
	 * The hostname of the radius server.
	 */
	private $hostname;

	/**
	 * The port of the radius server.
	 */
	private $port;

	/**
	 * The secret used when communicating with the radius server.
	 */
	private $secret;

	/**
	 * The timeout for contacting the radius server.
	 */
	private $timeout;

	/**
	 * The number of retries which should be attempted.
	 */
	private $retries;

	/**
	 * The attribute name where the username should be stored.
	 */
	private $usernameAttribute;

	/**
	 * The vendor for the RADIUS attributes we are interrested in.
	 */
	private $vendor;

	/**
	 * The vendor-specific attribute for the RADIUS attributes we are interrested in.
	 */
	private $vendorType;
	/**
	 * The NAS-Identifier that should be set in Access-Request packets.
	 */
	private $nasIdentifier;

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

		// Parse configuration.
		$config = SimpleSAML_Configuration::loadFromArray($config,
			'Authentication source ' . var_export($this->authId, TRUE));

		$this->servers = $config->getArray('servers', array());
		/* For backwards compatibility. */
		if (empty($this->servers)) {
			$this->hostname = $config->getString('hostname');
			$this->port = $config->getIntegerRange('port', 1, 65535, 1812);
			$this->secret = $config->getString('secret');
			$this->servers[] = array('hostname' => $this->hostname,
									 'port' => $this->port,
									 'secret' => $this->secret);
		}
		$this->timeout = $config->getInteger('timeout', 5);
		$this->retries = $config->getInteger('retries', 3);
		$this->usernameAttribute = $config->getString('username_attribute', NULL);
		$this->nasIdentifier = $config->getString('nas_identifier',
												  isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');

		$this->vendor = $config->getInteger('attribute_vendor', NULL);
		if ($this->vendor !== NULL) {
			$this->vendorType = $config->getInteger('attribute_vendor_type');
		}
	}


	/**
	 * Attempt to log in using the given username and password.
	 *
	 * @param string $username  The username the user wrote.
	 * @param string $password  The password the user wrote.
	 * @return array  Associative array with the users attributes.
	 */
	protected function login($username, $password) {
		assert('is_string($username)');
		assert('is_string($password)');

		$radius = radius_auth_open();

		/* Try to add all radius servers, trigger a failure if no one works. */
		$success = false;
		foreach ($this->servers as $server) {
			if (!isset($server['port'])) {
				$server['port'] = 1812;
			}
			if (!radius_add_server($radius, $server['hostname'], $server['port'], $server['secret'], 
								   $this->timeout, $this->retries)) {
				SimpleSAML_Logger::info("Could not connect to server: ".radius_strerror($radius));
				continue;
			}
			$success = true;
		}
		if (!$success) {
			throw new Exception('Error connecting to radius server, no servers available');
		}

		if (!radius_create_request($radius, RADIUS_ACCESS_REQUEST)) {
			throw new Exception('Error creating radius request: ' . radius_strerror($radius));
		}

		radius_put_attr($radius, RADIUS_USER_NAME, $username);
		radius_put_attr($radius, RADIUS_USER_PASSWORD, $password);

		if ($this->nasIdentifier != NULL)
			radius_put_attr($radius, RADIUS_NAS_IDENTIFIER, $this->nasIdentifier);

		$res = radius_send_request($radius);
		if ($res != RADIUS_ACCESS_ACCEPT) {
			switch ($res) {
			case RADIUS_ACCESS_REJECT:
				/* Invalid username or password. */
				throw new SimpleSAML_Error_Error('WRONGUSERPASS');
			case RADIUS_ACCESS_CHALLENGE:
				throw new Exception('Radius authentication error: Challenge requested, but not supported.');
			default:
				throw new Exception('Error during radius authentication: ' . radius_strerror($radius));
			}
		}

		/* If we get this far, we have a valid login. */

		$attributes = array();

		if ($this->usernameAttribute !== NULL) {
			$attributes[$this->usernameAttribute] = array($username);
		}

		if ($this->vendor === NULL) {
			/*
			 * We aren't interested in any vendor-specific attributes. We are
			 * therefore done now.
			 */
			return $attributes;
		}

		/* get AAI attribute sets. Contributed by Stefan Winter, (c) RESTENA */
		while ($resa = radius_get_attr($radius)) {

			if (!is_array($resa)) {
				throw new Exception('Error getting radius attributes: ' . radius_strerror($radius));
			}

			/* Use the received user name */
			if ($resa['attr'] == RADIUS_USER_NAME) {
				$attributes[$this->usernameAttribute] = array($resa['data']);
				continue;
			}

			if ($resa['attr'] !== RADIUS_VENDOR_SPECIFIC) {
				continue;
			}

			$resv = radius_get_vendor_attr($resa['data']);
			if (!is_array($resv)) {
				throw new Exception('Error getting vendor specific attribute: ' . radius_strerror($radius));
			}

			$vendor = $resv['vendor'];
			$attrv = $resv['attr'];
			$datav = $resv['data'];

			if ($vendor != $this->vendor || $attrv != $this->vendorType) {
				continue;
			}

			$attrib_name = strtok($datav,'=');
			$attrib_value = strtok('=');

			/* if the attribute name is already in result set, add another value */
			if (array_key_exists($attrib_name, $attributes)) {
				$attributes[$attrib_name][] = $attrib_value;
			} else {
				$attributes[$attrib_name] = array($attrib_value);
			}
		}
		/* end of contribution */

		return $attributes;
	}

}
