<?php
/**
 * ProjectSend system constants
 *
 * This file includes the most basic system options that cannot be
 * changed through the web interface, such as the version number,
 * php directives and the user and password length values.
 *
 * @package ProjectSend
 * @subpackage Core
 */

/**
 * Current version.
 * Updated only when releasing a new downloadable complete version.
 * Format: Major.Minor.Patch
 */
define('CURRENT_VERSION', '1.0.0');

/**
 * Define the system name, and the information that will be used
 * on the footer blocks.
 *
 */
define('SYSTEM_NAME','ProjectSend');
define('SYSTEM_URI','https://www.projectsend.org/');
define('SYSTEM_URI_LABEL','ProjectSend on github');
define('DONATIONS_URL','https://www.projectsend.org/donations/');

/**
 * Required software versions
 */
define('REQUIRED_VERSION_PHP', '5.6');
define('REQUIRED_VERSION_MYSQL', '5.0');

/**
 * Fix for including external files when on HTTPS.
 * Contribution by Scott Wright on
 * http://code.google.com/p/clients-oriented-ftp/issues/detail?id=230
 */
define('PROTOCOL', empty($_SERVER['HTTPS'])? 'http' : 'https');

/**
 * DEBUG constant effects:
 * - Changes the error_reporting php value
 * - Enables the PDOEX extension (on the database class) to count queries
 */
define('DEBUG', true);

/**
 * IS_DEV is set to true during development to show a sitewide remainder
 * of the app unreleased status.
 */
define('IS_DEV', false);

/**
 * This constant holds the current default charset
 */
define('CHARSET', 'UTF-8');

/**
 * Turn off reporting of PHP errors, warnings and notices.
 * On a development environment, it should be set to E_ALL for
 * complete debugging.
 *
 * @link http://www.php.net/manual/en/function.error-reporting.php
 */
if ( DEBUG === true ) {
	error_reporting(E_ALL);
}
else {
	error_reporting(0);
}

define('GLOBAL_TIME_LIMIT', 240*60);
define('UPLOAD_TIME_LIMIT', 120*60);
@set_time_limit(GLOBAL_TIME_LIMIT);

/**
 * This file ;)
 */
define('SYS_FILE', ROOT_DIR . __FILE__);

/**
 * Directories
 */
/** ProjectSen's classes and resources */
define('CORE_DIR', ROOT_DIR . '/core');
define('CLASSES_DIR', CORE_DIR . '/classes');
define('ADMIN_TEMPLATES_DIR', CORE_DIR . '/templates-admin');
define('INCLUDES_DIR', CORE_DIR . '/includes');

/** External */
define('LIB_DIR', ROOT_DIR . '/lib');

define('ASSETS_DIR', ROOT_DIR.'/assets');
define('ASSETS_CSS_DIR', ASSETS_DIR.'/css');
define('ASSETS_JS_DIR', ASSETS_DIR.'/js');
define('ASSETS_IMG_DIR', ASSETS_DIR.'/images');
define('ASSETS_VENDOR_DIR', ASSETS_DIR.'/vendor');

define('ASSETS_URL', '//assets');
define('ASSETS_CSS_URL', ASSETS_URL . '/css');
define('ASSETS_JS_URL', ASSETS_URL.'/js');
define('ASSETS_IMG_URL', ASSETS_URL.'/images');
define('ASSETS_VENDOR_URL', ASSETS_URL.'/vendor');

define('EMAIL_TEMPLATES_DIR', CORE_DIR . '/templates-email');

/**
 * Define the folder where uploaded files will reside
 */
define('UPLOAD_DIR', ROOT_DIR.'/upload');
define('UPLOADED_FILES_FOLDER', UPLOAD_DIR.'/files');
define('UPLOADED_FILES_URL', 'upload/files');
define('BRANDING_DIR',UPLOAD_DIR.'/branding');

/**
 * Check if the personal configuration file exists
 * Otherwise will start a configuration page
 *
 * @see includes/sys.config.sample.php
 */
define('CONFIG_DIR', ROOT_DIR.'/config');
define('CONFIG_FILE', CONFIG_DIR.'/sys.config.php');
define('CONFIG_SAMPLE', CONFIG_DIR.'/sys.config.sample.php');

if ( !file_exists( CONFIG_FILE ) ) {
	if ( !defined( 'IS_MAKE_CONFIG' ) ) {
		// the following script returns only after the creation of the configuration file
		if ( defined('IS_INSTALL') ) {
			header('Location:make-config.php');
			exit;
		}
		else {
			header('Location:install/make-config.php');
			exit;
		}
	}
}
else {
	require_once CONFIG_FILE;
}

/**
 * Database connection driver
 */
if (!defined('DB_DRIVER')) {
	define('DB_DRIVER', 'mysql');
}

/**
 * Check for available PDO drivers
 * Currently supported: MySQL
 */
$pdo_available_drivers = PDO::getAvailableDrivers();
if( (DB_DRIVER == 'mysql') && !defined('PDO::MYSQL_ATTR_INIT_COMMAND') ) {
	$app->add_error('requirement_pdo');
}

/**
 * Define the tables names
 */
if (!defined('TABLES_PREFIX')) {
	define('TABLES_PREFIX', 'tbl_');
}

$all_system_tables = array(
							'files',
							'files_relations',
							'downloads',
							'notifications',
							'options',
							'users',
							'groups',
							'members',
							'members_requests',
							'folders',
							'categories',
							'categories_relations',
							'actions_log',
							'password_reset',
						);
foreach ( $all_system_tables as $table ) {
	$const = strtoupper( 'table_' . $table );
	define( $const, TABLES_PREFIX . $table );
}

/**
 * This values affect both validation methods (client and server side)
 * and also the maxlength value of the form fields.
 */
define('MIN_USER_CHARS', 5);
define('MAX_USER_CHARS', 60);
define('MIN_PASS_CHARS', 5);
define('MAX_PASS_CHARS', 60);

define('MIN_GENERATE_PASS_CHARS', 10);
define('MAX_GENERATE_PASS_CHARS', 20);
/*
 * Cookie expiration time (in seconds).
 * Set by default to 30 days (60*60*24*30).
 */
define('COOKIE_EXP_TIME', 60*60*24*30);

/**
 * Time (in seconds) after which the session becomes invalid.
 * Default is disabled and time is set to a huge value (1 month)
 * Case uses must be analyzed before enabling this function
 */
define('SESSION_TIMEOUT_EXPIRE', true);
$session_expire_time = 31*24*60*60; // 31 days * 24 hours * 60 minutes * 60 seconds
define('SESSION_EXPIRE_TIME', $session_expire_time);

/** phpass */
define('HASH_COST_LOG2', 8);
define('HASH_PORTABLE', false);

/**
 * Define the RSS url to use on the home news list.
 */
define('NEWS_FEED_URI','https://www.projectsend.org/feed/');

/**
 * Define the Feed from where to take the latest version
 * number.
 */
define('UPDATES_FEED_URI','https://projectsend.org/updates/versions.xml');

/**
 * External links
 */
define('LINK_DOC_RECAPTCHA', 'https://developers.google.com/recaptcha/docs/start');
define('LINK_DOC_GOOGLE_SIGN_IN', 'https://developers.google.com/identity/protocols/OpenIDConnect');
