<?php


/**
 * The Negotiate module. Allows for password-less, secure login by Kerberos and Negotiate.
 *
 * @author Mathias Meisfjordskar, University of Oslo <mathias.meisfjordskar@usit.uio.no>
 * @package SimpleSAMLphp
 */
class sspmod_negotiate_Auth_Source_Negotiate extends SimpleSAML_Auth_Source
{

    // Constants used in the module
    const STAGEID = 'sspmod_negotiate_Auth_Source_Negotiate.StageId';

    protected $ldap = null;
    protected $backend = '';
    protected $hostname = '';
    protected $port = 389;
    protected $referrals = true;
    protected $enableTLS = false;
    protected $debugLDAP = false;
    protected $timeout = 30;
    protected $keytab = '';
    protected $base = array();
    protected $attr = 'uid';
    protected $subnet = null;
    protected $admin_user = null;
    protected $admin_pw = null;
    protected $attributes = null;


    /**
     * Constructor for this authentication source.
     *
     * @param array $info Information about this authentication source.
     * @param array $config The configuration of the module
     *
     * @throws Exception If the KRB5 extension is not installed or active.
     */
    public function __construct($info, $config)
    {
        assert('is_array($info)');
        assert('is_array($config)');

        if (!extension_loaded('krb5')) {
            throw new Exception('KRB5 Extension not installed');
        }

        // call the parent constructor first, as required by the interface
        parent::__construct($info, $config);

        $config = SimpleSAML_Configuration::loadFromArray($config);

        $this->backend = $config->getString('fallback');
        $this->hostname = $config->getString('hostname');
        $this->port = $config->getInteger('port', 389);
        $this->referrals = $config->getBoolean('referrals', true);
        $this->enableTLS = $config->getBoolean('enable_tls', false);
        $this->debugLDAP = $config->getBoolean('debugLDAP', false);
        $this->timeout = $config->getInteger('timeout', 30);
        $this->keytab = $config->getString('keytab');
        $this->base = $config->getArrayizeString('base');
        $this->attr = $config->getString('attr', 'uid');
        $this->subnet = $config->getArray('subnet', null);
        $this->admin_user = $config->getString('adminUser', null);
        $this->admin_pw = $config->getString('adminPassword', null);
        $this->attributes = $config->getArray('attributes', null);
    }


    /**
     * The inner workings of the module.
     *
     * Checks to see if client is in the defined subnets (if defined in config). Sends the client a 401 Negotiate and
     * responds to the result. If the client fails to provide a proper Kerberos ticket, the login process is handed over
     * to the 'fallback' module defined in the config.
     *
     * LDAP is used as a user metadata source.
     *
     * @param array &$state Information about the current authentication.
     */
    public function authenticate(&$state)
    {
        assert('is_array($state)');

        // set the default backend to config
        $state['LogoutState'] = array(
            'negotiate:backend' => $this->backend,
        );
        $state['negotiate:authId'] = $this->authId;


        // check for disabled SPs. The disable flag is store in the SP metadata
        if (array_key_exists('SPMetadata', $state) && $this->spDisabledInMetadata($state['SPMetadata'])) {
            $this->fallBack($state);
        }
        /* Go straight to fallback if Negotiate is disabled or if you are sent back to the IdP directly from the SP
        after having logged out. */
        $session = SimpleSAML_Session::getSessionFromRequest();
        $disabled = $session->getData('negotiate:disable', 'session');

        if ($disabled ||
            (!empty($_COOKIE['NEGOTIATE_AUTOLOGIN_DISABLE_PERMANENT']) &&
                $_COOKIE['NEGOTIATE_AUTOLOGIN_DISABLE_PERMANENT'] == 'True')
        ) {
            SimpleSAML_Logger::debug('Negotiate - session disabled. falling back');
            $this->fallBack($state);
            // never executed
            assert('FALSE');
        }
        $mask = $this->checkMask();
        if (!$mask) {
            $this->fallBack($state);
            // never executed
            assert('FALSE');
        }

        SimpleSAML_Logger::debug('Negotiate - authenticate(): looking for Negotate');
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            SimpleSAML_Logger::debug('Negotiate - authenticate(): Negotate found');
            $this->ldap = new SimpleSAML_Auth_LDAP(
                $this->hostname,
                $this->enableTLS,
                $this->debugLDAP,
                $this->timeout,
                $this->port,
                $this->referrals
            );

            list($mech, $data) = explode(' ', $_SERVER['HTTP_AUTHORIZATION'], 2);
            if (strtolower($mech) == 'basic') {
                SimpleSAML_Logger::debug('Negotiate - authenticate(): Basic found. Skipping.');
            } else {
                if (strtolower($mech) != 'negotiate') {
                    SimpleSAML_Logger::debug('Negotiate - authenticate(): No "Negotiate" found. Skipping.');
                }
            }

            $auth = new KRB5NegotiateAuth($this->keytab);
            // attempt Kerberos authentication
            try {
                $reply = $auth->doAuthentication();
            } catch (Exception $e) {
                SimpleSAML_Logger::error('Negotiate - authenticate(): doAuthentication() exception: '.$e->getMessage());
                $reply = null;
            }

            if ($reply) {
                // success! krb TGS received
                $user = $auth->getAuthenticatedUser();
                SimpleSAML_Logger::info('Negotiate - authenticate(): '.$user.' authenticated.');
                $lookup = $this->lookupUserData($user);
                if ($lookup) {
                    $state['Attributes'] = $lookup;
                    // Override the backend so logout will know what to look for
                    $state['LogoutState'] = array(
                        'negotiate:backend' => null,
                    );
                    SimpleSAML_Logger::info('Negotiate - authenticate(): '.$user.' authorized.');
                    SimpleSAML_Auth_Source::completeAuth($state);
                    // Never reached.
                    assert('FALSE');
                }
            } else {
                // Some error in the recieved ticket. Expired?
                SimpleSAML_Logger::info('Negotiate - authenticate(): Kerberos authN failed. Skipping.');
            }
        } else {
            // No auth token. Send it.
            SimpleSAML_Logger::debug('Negotiate - authenticate(): Sending Negotiate.');
            // Save the $state array, so that we can restore if after a redirect
            SimpleSAML_Logger::debug('Negotiate - fallback: '.$state['LogoutState']['negotiate:backend']);
            $id = SimpleSAML_Auth_State::saveState($state, self::STAGEID);
            $params = array('AuthState' => $id);

            $this->sendNegotiate($params);
            exit;
        }

        SimpleSAML_Logger::info('Negotiate - authenticate(): Client failed Negotiate. Falling back');
        $this->fallBack($state);
        /* The previous function never returns, so this code is never
           executed */
        assert('FALSE');
    }


    public function spDisabledInMetadata($spMetadata)
    {
        if (array_key_exists('negotiate:disable', $spMetadata)) {
            if ($spMetadata['negotiate:disable'] == true) {
                SimpleSAML_Logger::debug('Negotiate - SP disabled. falling back');
                return true;
            } else {
                SimpleSAML_Logger::debug('Negotiate - SP disable flag found but set to FALSE');
            }
        } else {
            SimpleSAML_Logger::debug('Negotiate - SP disable flag not found');
        }
        return false;
    }


    /**
     * checkMask() looks up the subnet config option and verifies
     * that the client is within that range.
     *
     * Will return TRUE if no subnet option is configured.
     *
     * @return boolean
     */
    public function checkMask()
    {
        // No subnet means all clients are accepted.
        if ($this->subnet === null) {
            return true;
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        foreach ($this->subnet as $cidr) {
            $ret = SimpleSAML\Utils\Net::ipCIDRcheck($cidr);
            if ($ret) {
                SimpleSAML_Logger::debug('Negotiate: Client "'.$ip.'" matched subnet.');
                return true;
            }
        }
        SimpleSAML_Logger::debug('Negotiate: Client "'.$ip.'" did not match subnet.');
        return false;
    }


    /**
     * Send the actual headers and body of the 401. Embedded in the body is a post that is triggered by JS if the client
     * wants to show the 401 message.
     *
     * @param array $params additional parameters to the URL in the URL in the body.
     */
    protected function sendNegotiate($params)
    {
        $url = htmlspecialchars(SimpleSAML_Module::getModuleURL('negotiate/backend.php', $params));
        $json_url = json_encode($url);

        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Negotiate', false);
        echo <<<EOF
<html>
 <head>
  <script type="text/javascript">window.location = $json_url</script>
  <title>Redirect to login</title>
 </head>
<body>
 <p>Your browser seems to have Javascript disabled. Please click <a href="$url">here</a>.</p>
</body>
</html>
EOF;
    }


    /**
     * Passes control of the login process to a different module.
     *
     * @param string $state Information about the current authentication.
     *
     * @throws SimpleSAML_Error_Error If couldn't determine the auth source.
     * @throws SimpleSAML_Error_Exception
     * @throws Exception
     */
    public static function fallBack(&$state)
    {
        $authId = $state['LogoutState']['negotiate:backend'];

        if ($authId === null) {
            throw new SimpleSAML_Error_Error(500, "Unable to determine auth source.");
        }
        $source = SimpleSAML_Auth_Source::getById($authId);

        try {
            $source->authenticate($state);
        } catch (SimpleSAML_Error_Exception $e) {
            SimpleSAML_Auth_State::throwException($state, $e);
        } catch (Exception $e) {
            $e = new SimpleSAML_Error_UnserializableException($e);
            SimpleSAML_Auth_State::throwException($state, $e);
        }
        // fallBack never returns after loginCompleted()
        SimpleSAML_Logger::debug('Negotiate: backend returned');
        self::loginCompleted($state);
    }


    /**
     * Strips away the realm of the Kerberos identifier, looks up what attributes to fetch from SP metadata and
     * searches the directory.
     *
     * @param string $user The Kerberos user identifier.
     *
     * @return string The DN to the user or NULL if not found.
     */
    protected function lookupUserData($user)
    {
        // Kerberos user names include realm. Strip that away.
        $pos = strpos($user, '@');
        if ($pos === false) {
            return null;
        }
        $uid = substr($user, 0, $pos);

        $this->adminBind();
        try {
            $dn = $this->ldap->searchfordn($this->base, $this->attr, $uid);
            return $this->ldap->getAttributes($dn, $this->attributes);
        } catch (SimpleSAML_Error_Exception $e) {
            SimpleSAML_Logger::debug('Negotiate - ldap lookup failed: '.$e);
            return null;
        }
    }


    /**
     * Elevates the LDAP connection to allow restricted lookups if
     * so configured. Does nothing if not.
     */
    protected function adminBind()
    {
        if ($this->admin_user === null) {
            // no admin user
            return;
        }
        SimpleSAML_Logger::debug(
            'Negotiate - authenticate(): Binding as system user '.var_export($this->admin_user, true)
        );

        if (!$this->ldap->bind($this->admin_user, $this->admin_pw)) {
            $msg = 'Unable to authenticate system user (LDAP_INVALID_CREDENTIALS) '.var_export($this->admin_user, true);
            SimpleSAML_Logger::error('Negotiate - authenticate(): '.$msg);
            throw new SimpleSAML_Error_AuthSource($msg);
        }
    }


    /**
     * Log out from this authentication source.
     *
     * This method either logs the user out from Negotiate or passes the
     * logout call to the fallback module.
     *
     * @param array &$state Information about the current logout operation.
     */
    public function logout(&$state)
    {
        assert('is_array($state)');
        // get the source that was used to authenticate
        $authId = $state['negotiate:backend'];
        SimpleSAML_Logger::debug('Negotiate - logout has the following authId: "'.$authId.'"');

        if ($authId === null) {
            $session = SimpleSAML_Session::getSessionFromRequest();
            $session->setData('negotiate:disable', 'session', true, 24 * 60 * 60);
            parent::logout($state);
        } else {
            $source = SimpleSAML_Auth_Source::getById($authId);
            $source->logout($state);
        }
    }
}
