<?php
/**
 * Requirements of basic system files.
 * It's included in every main page as the app starter.
 * 
 * @todo remove this file after using a proper router!
 * 
 * @package ProjectSend
 * @subpackage Core
 */

define('ROOT_DIR', __DIR__);

/* Short for DIRECTORY_SEPARATOR */
define('DS', DIRECTORY_SEPARATOR);

/* Directories */
define('CORE_DIR', ROOT_DIR . DS . 'app');
define('CORE_LANG_DIR', ROOT_DIR . DS . 'lang');
define('CLASSES_DIR', CORE_DIR . DS . 'classes');
define('ADMIN_TEMPLATES_DIR', CORE_DIR . DS . 'templates-admin');
define('FORMS_DIR', ADMIN_TEMPLATES_DIR . DS . 'forms');
define('AUTOLOAD_DIR', CORE_DIR . DS . 'autoload');
define('INCLUDES_DIR', CORE_DIR . DS . 'includes');

define('SYS_FILE', CORE_DIR . DS . "config" . DS . "config.php");
require_once SYS_FILE;

/* Composer dependencies */
require_once ROOT_DIR . '/vendor/autoload.php';

/* Actions logger */
global $logger;
$logger = new ProjectSend\LogActions();

/* Auth */
global $auth;
$auth = new ProjectSend\Auth();

/**
 * @todo initialize only when needed
 */
global $validation;
$validation = new ProjectSend\FormValidate();

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
require_once ROOT_DIR . '/includes/Google/Oauth2/service/Google_ServiceResource.php';
require_once ROOT_DIR . '/includes/Google/Oauth2/service/Google_Service.php';
require_once ROOT_DIR . '/includes/Google/Oauth2/service/Google_Model.php';
require_once ROOT_DIR . '/includes/Google/Oauth2/contrib/Google_Oauth2Service.php';
require_once ROOT_DIR . '/includes/Google/Oauth2/Google_Client.php';

/* Updates */
global $core_updates;
$core_updates = new ProjectSend\UpdatesCore();