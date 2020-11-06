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
session_start();

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
 * Current version.
 * Updated only when releasing a new downloadable complete version.
 */
define('CURRENT_VERSION', 'r1242');

/**
 * Required software versions
 */
define('REQUIRED_VERSION_PHP', '7.0');
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
define('IS_DEV', true);

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
	ini_set('display_errors', 'on');
	ini_set('error_reporting', 'E_ALL');
	ini_set('display_startup_errors', 'On');
	error_reporting(E_ALL);
}
else {
	error_reporting(0);
}

define('GLOBAL_TIME_LIMIT', 240*60);
define('UPLOAD_TIME_LIMIT', 120*60);
@set_time_limit(GLOBAL_TIME_LIMIT);

/**
 * Define the RSS url to use on the home news list.
 */
define('NEWS_FEED_URI','https://www.projectsend.org/serve/news');

/**
 * Define the Feed from where to take the latest version
 * number.
 */
define('UPDATES_FEED_URI','https://projectsend.org/serve/versions');

/**
 * Check if the personal configuration file exists
 * Otherwise will start a configuration page
 *
 * @see sys.config.sample.php
 */
if ( !file_exists(ROOT_DIR.'/includes/sys.config.php') ) {
	if ( !defined( 'IS_MAKE_CONFIG' ) ) {
		// the following script returns only after the creation of the configuration file
		if ( defined('IS_INSTALL') ) {
			header('Location:make-config.php');
		}
		else {
			header('Location:install/make-config.php');
		}
	}
}
else {
	include_once ROOT_DIR.'/includes/sys.config.php';
}

/**
 * Database connection driver
 */
if (!defined('DB_DRIVER')) {
	define('DB_DRIVER', 'mysql');
}

/**
 * Check for PDO extensions
 */
$pdo_available_drivers = PDO::getAvailableDrivers();
if( (DB_DRIVER == 'mysql') && !defined('PDO::MYSQL_ATTR_INIT_COMMAND') ) {
	echo '<h1>Missing a required extension</h1>';
	echo "<p>The system couldn't find the configuration the <strong>PDO extension for mysql</strong>.</p>
	<p>This extension is required for database comunication.</p>
	<p>You can install this extension via the package manager of your linux distro, most likely with one of these commands:</p>
	<ul>
		<li>sudo apt-get install php5-mysql   	<strong># debian/ubuntu</strong></li>
		<li>sudo yum install php-mysql   		<strong># centos/fedora</strong></li>
	</ul>
	<p>You also need to restart the webserver after the installation of PDO_mysql.</p>";
	exit;
}
if( (DB_DRIVER == 'mssql') && !in_array('dblib', $pdo_available_drivers) ) {
	echo '<h1>Missing a required extension</h1>';
	echo "<p>The system couldn't find the configuration the <strong>PDO extension for MS SQL Server</strong>.</p>
	<p>This extension is required for database comunication.</p>
	<p>You can install this extension via the package manager of your linux distro, most likely with one of these commands:</p>
	<ul>
		<li>sudo apt-get install php5-sybase	<strong># debian/ubuntu</strong></li>
		<li>sudo yum install php-mssql			<strong># centos/fedora (you need EPEL)</strong></li>
	</ul>
	<p>You also need to restart the webserver after the installation of PDO_mssql.</p>";
	exit;
}

/**
 * Define the tables names
 */
if (!defined('TABLES_PREFIX')) {
	define('TABLES_PREFIX', 'tbl_');
}
define('TABLE_FILES', TABLES_PREFIX . 'files');
define('TABLE_FILES_RELATIONS', TABLES_PREFIX . 'files_relations');
define('TABLE_DOWNLOADS', TABLES_PREFIX . 'downloads');
define('TABLE_NOTIFICATIONS', TABLES_PREFIX . 'notifications');
define('TABLE_OPTIONS', TABLES_PREFIX . 'options');
define('TABLE_USERS', TABLES_PREFIX . 'users');
define('TABLE_USER_META', TABLES_PREFIX . 'user_meta');
define('TABLE_GROUPS', TABLES_PREFIX . 'groups');
define('TABLE_MEMBERS', TABLES_PREFIX . 'members');
define('TABLE_MEMBERS_REQUESTS', TABLES_PREFIX . 'members_requests');
define('TABLE_FOLDERS', TABLES_PREFIX . 'folders');
define('TABLE_CATEGORIES', TABLES_PREFIX . 'categories');
define('TABLE_CATEGORIES_RELATIONS', TABLES_PREFIX . 'categories_relations');
define('TABLE_LOG', TABLES_PREFIX . 'actions_log');
define('TABLE_PASSWORD_RESET', TABLES_PREFIX . 'password_reset');

$original_basic_tables = array(
								TABLE_FILES,
								TABLE_OPTIONS,
								TABLE_USERS
							);

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

//$current_tables = array(TABLE_FILES,TABLE_FILES_RELATIONS,TABLE_OPTIONS,TABLE_USERS,TABLE_GROUPS,TABLE_MEMBERS,TABLE_FOLDERS,TABLES_PREFIX,TABLE_LOG,TABLE_CATEGORIES,TABLE_CATEGORIES_RELATIONS);

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

/* Password recovery */
define('PASSWORD_RECOVERY_TOKEN_EXPIRATION_TIME', 60*60*24);

/**
 * Time (in seconds) after which the session becomes invalid.
 * Default is disabled and time is set to a huge value (1 month)
 * Case uses must be analyzed before enabling this function
 */
define('SESSION_TIMEOUT_EXPIRE', true);
$session_expire_time = 31*24*60*60; // 31 days * 24 hours * 60 minutes * 60 seconds
define('SESSION_EXPIRE_TIME', $session_expire_time);

/* Define the folder where uploaded files will reside */
define('UPLOADED_FILES_ROOT', ROOT_DIR . DS . 'upload');
define('UPLOADED_FILES_DIR', UPLOADED_FILES_ROOT . DS . 'files');
define('THUMBNAILS_FILES_DIR', UPLOADED_FILES_ROOT . DS . 'thumbnails');
define('UPLOADED_FILES_URL', 'upload/files/');

/* Assets */
define('ASSETS_DIR', ROOT_DIR . DS . 'assets');
define('ASSETS_CSS_DIR', ASSETS_DIR . DS . 'css');
define('ASSETS_IMG_DIR', ASSETS_DIR . DS . 'img');
define('ASSETS_JS_DIR', ASSETS_DIR . DS . 'js');
define('ASSETS_LIB_DIR', ASSETS_DIR . DS . 'lib');

/** Directories */
define('CORE_LANG_DIR', ROOT_DIR . DS . 'lang');
define('INCLUDES_DIR', ROOT_DIR . DS . 'includes');
define('FORMS_DIR', INCLUDES_DIR . DS . 'forms');
define('ADMIN_VIEWS_DIR', ROOT_DIR);
define('EMAIL_TEMPLATES_DIR', ADMIN_VIEWS_DIR . DS . 'emails');
define('TEMPLATES_DIR', ROOT_DIR . DS . 'templates');
define('JSON_CACHE_DIR', ROOT_DIR . DS . 'cache');

/* Branding */
define('ADMIN_UPLOADS_DIR', UPLOADED_FILES_ROOT . DS . 'admin');
define('LOGO_MAX_WIDTH', 300);
define('LOGO_MAX_HEIGHT', 300);
define('DEFAULT_LOGO_FILENAME', 'projectsend-logo.svg');

/* Thumbnails */
define('THUMBS_MAX_WIDTH', 300);
define('THUMBS_MAX_HEIGHT', 300);
define('THUMBS_QUALITY', 90);

/* Widgets */
define('WIDGETS_FOLDER',ROOT_DIR.'/includes/widgets/');

/* Default e-mail templates files */
define('EMAIL_TEMPLATE_HEADER', 'header.html');
define('EMAIL_TEMPLATE_FOOTER', 'footer.html');
define('EMAIL_TEMPLATE_NEW_CLIENT', 'new-client.html');
define('EMAIL_TEMPLATE_NEW_CLIENT_SELF', 'new-client-self.html');
define('EMAIL_TEMPLATE_CLIENT_EDITED', 'client-edited.html');
define('EMAIL_TEMPLATE_NEW_USER', 'new-user.html');
define('EMAIL_TEMPLATE_ACCOUNT_APPROVE', 'account-approve.html');
define('EMAIL_TEMPLATE_ACCOUNT_DENY', 'account-deny.html');
define('EMAIL_TEMPLATE_NEW_FILE_BY_USER', 'new-file-by-user.html');
define('EMAIL_TEMPLATE_NEW_FILE_BY_CLIENT', 'new-file-by-client.html');
define('EMAIL_TEMPLATE_PASSWORD_RESET', 'password-reset.html');

/** Passwords */
define('HASH_COST_LOG2', 8);
define('HASH_PORTABLE', false);

/**
 * Footable
 * Define the amount of items to show
 * @todo Get this value off a cookie if it exists.
 */
define('FOOTABLE_PAGING_NUMBER', '10');
define('FOOTABLE_PAGING_NUMBER_LOG', '15');
define('RESULTS_PER_PAGE', '10');
define('RESULTS_PER_PAGE_LOG', '15');

/**
 * External links
 */
define('LINK_DOC_RECAPTCHA', 'https://developers.google.com/recaptcha/docs/start');
define('LINK_DOC_GOOGLE_SIGN_IN', 'https://developers.google.com/identity/protocols/OpenIDConnect');
define('LINK_DOC_FACEBOOK_LOGIN', 'https://developers.facebook.com/docs/facebook-login/');
define('LINK_DOC_LINKEDIN_LOGIN', 'https://www.linkedin.com/developers/');