<?php
/**
 * PHPStan Bootstrap File for ProjectSend
 */

// Define constants that PHPStan needs to understand
define('IS_INSTALL', false);
define('IS_TESTING', true);

// Set up the application root
define('ABS_PARENT', __DIR__);

// Include the main bootstrap to get all the constants and functions
require_once __DIR__ . '/bootstrap.php';

// Define any additional constants that might be missing in static analysis
if (!defined('CURRENT_USER_ID')) {
    define('CURRENT_USER_ID', 1);
}

if (!defined('CURRENT_USER_LEVEL')) {
    define('CURRENT_USER_LEVEL', 9);
}

if (!defined('CURRENT_USER_ROLE')) {
    define('CURRENT_USER_ROLE', 'system_admin');
}

// Mock global variables that PHPStan might encounter
global $dbh, $flash, $auth, $permissions;