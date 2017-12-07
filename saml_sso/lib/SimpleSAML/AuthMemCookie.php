<?php


/**
 * This is a helper class for the Auth MemCookie module.
 * It handles the configuration, and implements the logout handler.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_AuthMemCookie
{

    /**
     * @var SimpleSAML_AuthMemCookie This is the singleton instance of this class.
     */
    private static $instance = null;


    /**
     * @var SimpleSAML_Configuration The configuration for Auth MemCookie.
     */
    private $amcConfig;


    /**
     * This function is used to retrieve the singleton instance of this class.
     *
     * @return SimpleSAML_AuthMemCookie The singleton instance of this class.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new SimpleSAML_AuthMemCookie();
        }

        return self::$instance;
    }


    /**
     * This function implements the constructor for this class. It loads the Auth MemCookie configuration.
     */
    private function __construct()
    {
        // load AuthMemCookie configuration
        $this->amcConfig = SimpleSAML_Configuration::getConfig('authmemcookie.php');
    }


    /**
     * Retrieve the authentication source that should be used to authenticate the user.
     *
     * @return string The login type which should be used for Auth MemCookie.
     */
    public function getAuthSource()
    {
        return $this->amcConfig->getString('authsource');
    }


    /**
     * This function retrieves the name of the cookie from the configuration.
     *
     * @return string The name of the cookie.
     * @throws Exception If the value of the 'cookiename' configuration option is invalid.
     */
    public function getCookieName()
    {
        $cookieName = $this->amcConfig->getString('cookiename', 'AuthMemCookie');
        if (!is_string($cookieName) || strlen($cookieName) === 0) {
            throw new Exception(
                "Configuration option 'cookiename' contains an invalid value. This option should be a string."
            );
        }

        return $cookieName;
    }


    /**
     * This function retrieves the name of the attribute which contains the username from the configuration.
     *
     * @return string The name of the attribute which contains the username.
     */
    public function getUsernameAttr()
    {
        $usernameAttr = $this->amcConfig->getString('username', null);

        return $usernameAttr;
    }


    /**
     * This function retrieves the name of the attribute which contains the groups from the configuration.
     *
     * @return string The name of the attribute which contains the groups.
     */
    public function getGroupsAttr()
    {
        $groupsAttr = $this->amcConfig->getString('groups', null);

        return $groupsAttr;
    }


    /**
     * This function creates and initializes a Memcache object from our configuration.
     *
     * @return Memcache A Memcache object initialized from our configuration.
     */
    public function getMemcache()
    {
        $memcacheHost = $this->amcConfig->getString('memcache.host', '127.0.0.1');
        $memcachePort = $this->amcConfig->getInteger('memcache.port', 11211);

        $memcache = new Memcache;

        foreach (explode(',', $memcacheHost) as $memcacheHost) {
            $memcache->addServer($memcacheHost, $memcachePort);
        }

        return $memcache;
    }


    /**
     * This function logs the user out by deleting the session information from memcache.
     */
    private function doLogout()
    {
        $cookieName = $this->getCookieName();

        // check if we have a valid cookie
        if (!array_key_exists($cookieName, $_COOKIE)) {
            return;
        }

        $sessionID = $_COOKIE[$cookieName];

        // delete the session from memcache
        $memcache = $this->getMemcache();
        $memcache->delete($sessionID);

        // delete the session cookie
        \SimpleSAML\Utils\HTTP::setCookie($cookieName, null);
    }


    /**
     * This function implements the logout handler. It deletes the information from Memcache.
     */
    public static function logoutHandler()
    {
        self::getInstance()->doLogout();
    }
}
