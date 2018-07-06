<?php
/**
 * Requirements of basic system files.
 * It's included in every main page as the app starter.
 * 
 * @todo refactor this file after using a proper router!
 * 
 * @package ProjectSend
 * @subpackage Core
 */

define('ROOT_DIR', __DIR__);

define('DS', DIRECTORY_SEPARATOR);

/* App config */
define('CORE_DIR', ROOT_DIR . DS . 'app');
define('SYS_FILE', CORE_DIR . DS . "config" . DS . "config.php");
require_once SYS_FILE;

/* Composer dependencies */
require_once ROOT_DIR . '/vendor/autoload.php';

/* System messages shown before the main content */
global $messages;
$messages = new \ProjectSend\Messages();

/* Check php and database engine versions requirements, then extensions */
check_versions_requirements();
check_extensions_requirements();

/* Check if we are on a development version */
if ( IS_DEV == true ) {
    $message = __('You are using a development version. Some features may be unfinished or not working correctly.', 'cftp_admin');
    $messages->add(
        'warning', $message, array(
            'id' => 'dev_version',
            'add_notice' => true,
        )
    );
}

/* Actions logger */
global $logger;
$logger = new \ProjectSend\LogActions();

/* Auth */
global $auth;
$auth = new \ProjectSend\Auth();

/**
 * @todo initialize only when needed
 */
global $validation;
$validation = new \ProjectSend\FormValidate();

/** Custom functions files */
$requires = [
    'custom.php',
];
foreach ( $requires as $file ) {
    $location = INCLUDES_DIR . DS . $file;
    if ( file_exists( $location ) ) {
        require_once $location;
    }
}

/**
 * Google Login
 * 
 * @todo replace with composer dependencies
 */
require_once CORE_DIR . '/dependencies/Google/Oauth2/service/Google_ServiceResource.php';
require_once CORE_DIR . '/dependencies/Google/Oauth2/service/Google_Service.php';
require_once CORE_DIR . '/dependencies/Google/Oauth2/service/Google_Model.php';
require_once CORE_DIR . '/dependencies/Google/Oauth2/contrib/Google_Oauth2Service.php';
require_once CORE_DIR . '/dependencies/Google/Oauth2/Google_Client.php';

/* Updates */
global $core_updates;
$core_updates = new \ProjectSend\UpdatesCore();
$core_updates->apply_legacy_updates();