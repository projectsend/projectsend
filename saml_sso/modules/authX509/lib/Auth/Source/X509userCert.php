<?php

/**
 * This class implements x509 certificate authentication with
 * certificate validation against an LDAP directory.
 *
 * @author Emmanuel Dreyfus <manu@netbsd.org>
 * @package SimpleSAMLphp
 */
class sspmod_authX509_Auth_Source_X509userCert extends SimpleSAML_Auth_Source {

	/**
	 * x509 attributes to use from the certificate
	 * for searching the user in the LDAP directory.
	 */
	private $x509attributes = array('UID' => 'uid');


	/**
	 * LDAP attribute containing the user certificate
	 */
	private $ldapusercert = array('userCertificate;binary');


	/**
	 * LDAPConfigHelper object
	 */
	private $ldapcf;


	/**
	 * Constructor for this authentication source.
	 *
	 * All subclasses who implement their own constructor must call this
	 * constructor before using $config for anything.
	 *
	 * @param array $info  Information about this authentication source.
	 * @param array &$config  Configuration for this authentication source.
	 */
	public function __construct($info, &$config) {
		assert('is_array($info)');
		assert('is_array($config)');

		if (isset($config['authX509:x509attributes']))
			$this->x509attributes =
				$config['authX509:x509attributes'];

		if (array_key_exists('authX509:ldapusercert', $config))
			$this->ldapusercert =
				$config['authX509:ldapusercert'];

		parent::__construct($info, $config);

		$this->ldapcf = new sspmod_ldap_ConfigHelper($config,
			'Authentication source ' . var_export($this->authId, TRUE));

		return;
	}


	/**
	 * Convert certificate from PEM to DER
	 *
	 * @param array $pem_data  PEM-encoded certificate
	 */
	private function pem2der($pem_data) {
		$begin = "CERTIFICATE-----";
		$end   = "-----END";
		$pem_data = substr($pem_data,
			strpos($pem_data, $begin)+strlen($begin));
		$pem_data = substr($pem_data, 0, strpos($pem_data, $end));
		$der = base64_decode($pem_data);
		return $der;
	}


	/**
	 * Convert certificate from DER to PEM
	 *
	 * @param array $der_data  DER-encoded certificate
	 */
	private function der2pem($der_data) {
		$pem = chunk_split(base64_encode($der_data), 64, "\n");
		$pem = "-----BEGIN CERTIFICATE-----\n".$pem.
			"-----END CERTIFICATE-----\n";
		return $pem;
	}


	/**
	 * Finish a failed authentication.
	 *
	 * This function can be overloaded by a child authentication
	 * class that wish to perform some operations on failure
	 *
	 * @param array &$state  Information about the current authentication.
	 */
	public function authFailed(&$state) {
		$config = SimpleSAML_Configuration::getInstance();

		$t = new SimpleSAML_XHTML_Template($config,
			'authX509:X509error.php');
		$t->data['errorcode'] = $state['authX509.error'];

		$t->show();
		exit();
	}


	/**
	 * Validate certificate and login
	 *
	 * This function try to validate the certificate.
	 * On success, the user is logged in without going through
	 * o login page.
	 * On failure, The authX509:X509error.php template is
	 * loaded.
	 *
	 * @param array &$state  Information about the current authentication.
	 */
	public function authenticate(&$state) {
		assert('is_array($state)');
		$ldapcf = $this->ldapcf;

		if (!isset($_SERVER['SSL_CLIENT_CERT']) ||
		    ($_SERVER['SSL_CLIENT_CERT'] == '')) {
			$state['authX509.error'] = "NOCERT";
			$this->authFailed($state);
			assert('FALSE'); // NOTREACHED
			return;
		}

		$client_cert = $_SERVER['SSL_CLIENT_CERT'];
		$client_cert_data = openssl_x509_parse($client_cert);
		if ($client_cert_data == FALSE) {
			SimpleSAML_Logger::error('authX509: invalid cert');
			$state['authX509.error'] = "INVALIDCERT";
			$this->authFailed($state);

			assert('FALSE'); // NOTREACHED
			return;
		}

		$dn = NULL;
		foreach ($this->x509attributes as $x509_attr => $ldap_attr) {
			/* value is scalar */
			if (array_key_exists($x509_attr, $client_cert_data['subject'])) {
				$value = $client_cert_data['subject'][$x509_attr];
				SimpleSAML_Logger::info('authX509: cert '.
				                        $x509_attr.' = '.$value);
				$dn = $ldapcf->searchfordn($ldap_attr, $value, TRUE);
				if ($dn !== NULL)
					break;
			}
		}

		if ($dn === NULL) {
			SimpleSAML_Logger::error('authX509: cert has '.
			                         'no matching user in LDAP');
			$state['authX509.error'] = "UNKNOWNCERT";
			$this->authFailed($state);

			assert('FALSE'); /* NOTREACHED */
			return;
		}

		if ($this->ldapusercert === NULL) { // do not check for certificate match
			$attributes = $ldapcf->getAttributes($dn);
			assert('is_array($attributes)');
			$state['Attributes'] = $attributes;
			$this->authSuccesful($state);

			assert('FALSE'); /* NOTREACHED */
			return;
		}

		$ldap_certs = $ldapcf->getAttributes($dn, $this->ldapusercert);
		if ($ldap_certs === FALSE) {
			SimpleSAML_Logger::error('authX509: no certificate '.
			                         'found in LDAP for dn='.$dn);
			$state['authX509.error'] = "UNKNOWNCERT";
			$this->authFailed($state);

			assert('FALSE'); /* NOTREACHED */
			return;
		}


		$merged_ldapcerts = array();
		foreach ($this->ldapusercert as $attr)
			$merged_ldapcerts = array_merge($merged_ldapcerts,
			                                $ldap_certs[$attr]);
		$ldap_certs = $merged_ldapcerts;

		foreach ($ldap_certs as $ldap_cert) {
			$pem = $this->der2pem($ldap_cert);
			$ldap_cert_data = openssl_x509_parse($pem);
			if($ldap_cert_data == FALSE) {
				SimpleSAML_Logger::error('authX509: cert in '.
				                         'LDAP in invalid for '.
				                         'dn = '.$dn);
				continue;
			}

			if ($ldap_cert_data === $client_cert_data) {
				$attributes = $ldapcf->getAttributes($dn);
				assert('is_array($attributes)');
				$state['Attributes'] = $attributes;
				$this->authSuccesful($state);

				assert('FALSE'); /* NOTREACHED */
				return;
			}
		}

		SimpleSAML_Logger::error('authX509: no matching cert in '.
		                         'LDAP for dn = '.$dn);
		$state['authX509.error'] = "UNKNOWNCERT";
		$this->authFailed($state);

		assert('FALSE'); /* NOTREACHED */
		return;
	}


	/**
	 * Finish a succesfull authentication.
	 *
	 * This function can be overloaded by a child authentication
	 * class that wish to perform some operations after login.
	 *
	 * @param array &$state  Information about the current authentication.
	 */
	public function authSuccesful(&$state) {
		SimpleSAML_Auth_Source::completeAuth($state);

		assert('FALSE'); /* NOTREACHED */
		return;
	}

}
