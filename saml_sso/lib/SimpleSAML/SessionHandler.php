<?php


/**
 * This file is part of SimpleSAMLphp. See the file COPYING in the
 * root of the distribution for licence information.
 *
 * This file defines a base class for session handling.
 * Instantiation of session handler objects should be done through
 * the class method getSessionHandler().
 *
 * @author Olav Morken, UNINETT AS. <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
abstract class SimpleSAML_SessionHandler
{


    /**
     * This static variable contains a reference to the current
     * instance of the session handler. This variable will be NULL if
     * we haven't instantiated a session handler yet.
     *
     * @var SimpleSAML_SessionHandler
     */
    protected static $sessionHandler = null;


    /**
     * This function retrieves the current instance of the session handler.
     * The session handler will be instantiated if this is the first call
     * to this function.
     *
     * @return SimpleSAML_SessionHandler The current session handler.
     */
    public static function getSessionHandler()
    {
        if (self::$sessionHandler === null) {
            self::createSessionHandler();
        }

        return self::$sessionHandler;
    }


    /**
     * This constructor is included in case it is needed in the the
     * future. Including it now allows us to write parent::__construct() in
     * the subclasses of this class.
     */
    protected function __construct()
    {
    }


    /**
     * Create a new session id.
     *
     * @return string The new session id.
     */
    abstract public function newSessionId();


    /**
     * Retrieve the session ID saved in the session cookie, if there's one.
     *
     * @return string|null The session id saved in the cookie or null if no session cookie was set.
     */
    abstract public function getCookieSessionId();


    /**
     * Retrieve the session cookie name.
     *
     * @return string The session cookie name.
     */
    abstract public function getSessionCookieName();


    /**
     * Save the session.
     *
     * @param SimpleSAML_Session $session The session object we should save.
     */
    abstract public function saveSession(SimpleSAML_Session $session);


    /**
     * Load the session.
     *
     * @param string|NULL $sessionId The ID of the session we should load, or null to use the default.
     *
     * @return SimpleSAML_Session|null The session object, or null if it doesn't exist.
     */
    abstract public function loadSession($sessionId = null);


    /**
     * Set a session cookie.
     *
     * @param string $sessionName The name of the session.
     * @param string|null $sessionID The session ID to use. Set to null to delete the cookie.
     * @param array|null $cookieParams Additional parameters to use for the session cookie.
     *
     * @throws \SimpleSAML\Error\CannotSetCookie If we can't set the cookie.
     */
    abstract public function setCookie($sessionName, $sessionID, array $cookieParams = null);


    /**
     * Initialize the session handler.
     *
     * This function creates an instance of the session handler which is
     * selected in the 'session.handler' configuration directive. If no
     * session handler is selected, then we will fall back to the default
     * PHP session handler.
     */
    private static function createSessionHandler()
    {

        $store = SimpleSAML_Store::getInstance();
        if ($store === false) {
            self::$sessionHandler = new SimpleSAML_SessionHandlerPHP();
        } else {
            /** @var SimpleSAML_Store $store At this point, $store can only be an object */
            self::$sessionHandler = new SimpleSAML_SessionHandlerStore($store);
        }
    }


    /**
     * Check whether the session cookie is set.
     *
     * This function will only return false if is is certain that the cookie isn't set.
     *
     * @return bool True if it was set, false if not.
     */
    public function hasSessionCookie()
    {
        return true;
    }


    /**
     * Get the cookie parameters that should be used for session cookies.
     *
     * @return array An array with the cookie parameters.
     * @link http://www.php.net/manual/en/function.session-get-cookie-params.php
     */
    public function getCookieParams()
    {
        $config = SimpleSAML_Configuration::getInstance();

        return array(
            'lifetime' => $config->getInteger('session.cookie.lifetime', 0),
            'path'     => $config->getString('session.cookie.path', '/'),
            'domain'   => $config->getString('session.cookie.domain', null),
            'secure'   => $config->getBoolean('session.cookie.secure', false),
            'httponly' => true,
        );
    }
}
