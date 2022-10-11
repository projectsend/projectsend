<?php
namespace ProjectSend\Classes;

class Csrf {
    /**
     * Generate a new csrf protection token with a cryptographically secure random generator
     *
     * @return string
     */
    public static function getCsrfToken()
    {
        if(!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function addCsrf()
    {
        echo '<input type="hidden" name="csrf_token" value="'.self::getCsrfToken().'" />';
    }
    
    /**
     * Validates the send csrf token with a stable string comparison algorithm.
     * Do not optimize for speed!!!
     *
     * @return bool
     */
    public static function validateCsrfToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_REQUEST['csrf_token']);
    }
}