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
 */
define('CURRENT_VERSION', 'r672');
// corresponds to: "Allow clients to delete their own files (optional)"

/**
 * Fix for including external files when on HTTPS.
 * Contribution by Scott Wright on
 * http://code.google.com/p/clients-oriented-ftp/issues/detail?id=230
 */
define('PROTOCOL', empty($_SERVER['HTTPS'])? 'http' : 'https');

/**
 * Turn off reporting of PHP errors, warnings and notices.
 * On a development environment, it should be set to E_ALL for
 * complete debugging.
 *
 * @link http://www.php.net/manual/en/function.error-reporting.php
 */
error_reporting(0);

define('GLOBAL_TIME_LIMIT', 240*60);
define('UPLOAD_TIME_LIMIT', 120*60);
@set_time_limit(GLOBAL_TIME_LIMIT);

/**
 * Define the RSS url to use on the home news list.
 */
define('NEWS_FEED_URI','http://www.projectsend.org/feed/');

/**
 * Define the Feed from where to take the latest version
 * number.
 */
define('UPDATES_FEED_URI','http://projectsend.org/updates/versions.xml');

/**
 * Check if the personal configuration file exists
 * Otherwise will start a configuration page
 *
 * @see sys.config.sample.php
 */
if ( !file_exists(ROOT_DIR.'/includes/sys.config.php') && !defined( 'IS_MAKE_CONFIG' ) ) {
	// the following script returns only after the creation of the configuration file
	if ( defined('IS_INSTALL') ) {
		header('Location:make-config.php');
	}
	else {
		header('Location:install/make-config.php');
	}
}
else {
	include(ROOT_DIR.'/includes/sys.config.php');
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
define('TABLE_GROUPS', TABLES_PREFIX . 'groups');
define('TABLE_MEMBERS', TABLES_PREFIX . 'members');
define('TABLE_FOLDERS', TABLES_PREFIX . 'folders');
define('TABLE_LOG', TABLES_PREFIX . 'actions_log');
define('TABLE_PASSWORD_RESET', TABLES_PREFIX . 'password_reset');

$current_tables = array(
						TABLE_FILES,
						TABLE_OPTIONS,
						TABLE_USERS
					);
//$current_tables = array(TABLE_FILES,TABLE_FILES_RELATIONS,TABLE_OPTIONS,TABLE_USERS,TABLE_GROUPS,TABLE_MEMBERS,TABLE_FOLDERS,TABLE_LOG);

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
 * Define the folder where uploaded files will reside
 */
define('UPLOADED_FILES_FOLDER', ROOT_DIR.'/upload/files/');
define('UPLOADED_FILES_URL', '/upload/files/');

/**
 * Define the folder where the uploaded files are stored before
 * being assigned to any client.
 *
 * Also, this is the folder where files are searched for when
 * using the Import from FTP feature.
 *
 * @ Deprecated
 */
define('USER_UPLOADS_TEMP_FOLDER', ROOT_DIR.'/upload/temp');
define('CLIENT_UPLOADS_TEMP_FOLDER', ROOT_DIR.'/upload/temp');

/**
 * Define the system name, and the information that will be used
 * on the footer blocks.
 *
 */
define('SYSTEM_URI','http://www.projectsend.org/');
define('SYSTEM_URI_LABEL','ProjectSend on github');
define('DONATIONS_URL','http://www.projectsend.org/donations/');
/** Previously cFTP */
define('SYSTEM_NAME','ProjectSend');

define('LOGO_FOLDER',ROOT_DIR.'/img/custom/logo/');
define('LOGO_THUMB_FOLDER',ROOT_DIR.'/img/custom/thumbs/');

/** phpass */
define('HASH_COST_LOG2', 8);
define('HASH_PORTABLE', false);


/**
 * Database connection driver
 */
if (!defined('DB_DRIVER')) {
	define('DB_DRIVER', 'mysql');
}
?>