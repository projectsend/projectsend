<?php
/** Connect to the DB */
global $dbh;
if ( defined('DB_NAME') ) {
    try {
        switch ( DB_DRIVER ) {
            default:
            case 'mysql':
                if ( DEBUG === true ) {
                    $dbh = new ProjectSend\Database\PDOEx("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
                }
                else {
                    $dbh = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
                }
                break;
        }

        $dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $dbh->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
    }
    catch(PDOException $e) {
    /*
        print "Error!: " . $e->getMessage() . "<br/>";
        die();
    */
        return false;
    }
}

/** Get system options */
$options = new ProjectSend\Options();
$options->make();

/**
 * @todo initialize only when needed
 */
global $validation;
$validation = new ProjectSend\FormValidate();

/**
 * Directories and URLs constants
 */
if (defined('BASE_URI')) {
    /** Directories */
    define('ASSETS_DIR','assets/');
    define('ASSETS_COMPILED_DIR','assets/dist/');
    define('BOWER_DEPENDENCIES_DIR','bower_components/');
    define('COMPOSER_DEPENDENCIES_DIR','vendor/');
    define('NPM_DEPENDENCIES_DIR','node_modules/');
    define('EMAIL_TEMPLATES_URI', BASE_URI . '/app/templates-email');
    define('EMAIL_TEMPLATES_DIR', CORE_DIR . '/templates-email');
    define('TEMPLATES_DIR', CORE_DIR . '/templates');

    /** Selected template path */
    define('TEMPLATE_PATH', TEMPLATES_DIR . DS . SELECTED_CLIENTS_TEMPLATE . DS . 'template.php');

    define('LOGO_FOLDER',ROOT_DIR.DS.'img'.DS.'custom'.DS.'logo'.DS);
    define('LOGO_THUMB_FOLDER',ROOT_DIR.DS.'img'.DS.'custom'.DS.'thumbs'.DS);
    define('LOGO_MAX_WIDTH',300);
    define('LOGO_MAX_HEIGHT',300);

    /** Thumbnails */
    define('THUMBS_MAX_WIDTH',300);
    define('THUMBS_MAX_HEIGHT',300);
    define('THUMBS_QUALITY',90);

    /**Widgets files */
    define('WIDGETS_FOLDER',ADMIN_TEMPLATES_DIR.DS . 'widgets' . DS);
    define('WIDGETS_URL',BASE_URI . 'app/templates-admin/widgets/');

    /** SimpleImage thumbnails */
    define('THUMBNAILS_FILES_DIR', ROOT_DIR.'/upload/thumbnails');
    define('THUMBNAILS_FILES_URL', BASE_URI.'upload/thumbnails');

    /** Landing page for public groups and files */
    define('PUBLIC_DOWNLOAD_URI',BASE_URI.'download.php');
    define('PUBLIC_LANDING_URI',BASE_URI.'public.php');
    define('PUBLIC_GROUP_URI',BASE_URI.'public.php');
    define('ASSETS_CSS_URI',BASE_URI . ASSETS_COMPILED_DIR . 'css/');
    define('ASSETS_JS_URI',BASE_URI . ASSETS_COMPILED_DIR . 'js/');
    define('ASSETS_IMG_URI',BASE_URI . ASSETS_DIR . 'img/');
    define('ASSETS_IMG_DIR',ROOT_DIR . DS . ASSETS_DIR . 'img/');
    define('INCLUDES_URI', BASE_URI . 'app/includes');
    define('BOWER_DEPENDENCIES_URI',BASE_URI . BOWER_DEPENDENCIES_DIR);
    define('COMPOSER_DEPENDENCIES_URI',BASE_URI . COMPOSER_DEPENDENCIES_DIR);
    define('NPM_DEPENDENCIES_URI',BASE_URI . NPM_DEPENDENCIES_DIR);

    /** Recaptcha */
    if (
        RECAPTCHA_ENABLED == 1 &&
        !empty(RECAPTCHA_SITE_KEY) &&
        !empty(RECAPTCHA_SECRET_KEY)
    )
    {
        define('RECAPTCHA_AVAILABLE', true);
    }

    /**
     * Footable
     * Define the amount of items to show
     * @todo Get this value from a cookie if it exists.
     */
    if (!defined('FOOTABLE_PAGING_NUMBER')) {
        define('FOOTABLE_PAGING_NUMBER', '10');
        define('FOOTABLE_PAGING_NUMBER_LOG', '15');
    }
    if (!defined('RESULTS_PER_PAGE')) {
        define('RESULTS_PER_PAGE', '10');
        define('RESULTS_PER_PAGE_LOG', '15');
    }
}

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