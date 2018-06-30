<?php
/**
 * ProjectSend personal configuration file
 *
 * This file contains the database connection information, the language
 * definition, the maximum allowed filesize for uploads and the encoding
 * that will be used on the sent e-mails (for new client, new user and
 * new file).
 *
 * It be renamed to sys.config.php for ProjectSend to recognize
 * it, and the database information must be completed before installing
 * the software.
 * The filesize value and the character encoding can be changed at anytime.
 *
 * When downloading a new ProjectSend version, this file will not be
 * overwritten, as the version included by default is named
 * sys.config.sample.php
 *
 * @package ProjectSend
 * 
 */

/**
 * Enter your database connection information here
 * If you have doubts about this values, consult your web hosting provider.
 */

/**
 * Database driver to use with PDO.
 * Possible options: mysql, mssql
*/
define('DB_DRIVER', 'mysql');

/** Database name */
define('DB_NAME', 'psmourik');

/** Database host (in most cases it's localhost) */
define('DB_HOST', '127.0.0.1');

/** Database username (must be assigned to the database) */
define('DB_USER', 'root');

/** Database password */
define('DB_PASSWORD', '');

/**
 * Prefix for the tables. Set to something other than tbl_ for increased
 * security onr in case you want more than 1 installations on the same database.
 */
define('TABLES_PREFIX', 'tbl_');

/*
 * Global site language definition
 *
 * For this setting to work on the back-end (log-in, administration,
 * and the installation pages, and the e-mails sent by the system), a file named
 * x.mo (where x is the value that you define here) must exist on the folder
 * /lang.
 *
 * The uploader language strings are loaded from the file x.mo (see above) that
 * is located on the folder /includes/plupload/js/i18n/
 *
 * If you want to apply this setting to the client's file lists, you must
 * have a file named x.mo (see above) on your selected template folder
 * (eg: /templates/default/lang).
 *
 * English language files are included by default.
 *
 * This setting can be changed at anytime and the language will be applied
 * immediately.
 *
 */
define('SITE_LANG','es');

/**
 * Define a maximum size (in mb.) that is allowed on each file to be uploaded.
 */
define('MAX_FILESIZE',2048); 

/**
 * Encoding to use on the e-mails sent to new clients, users, files, etc.
 */
define('EMAIL_ENCODING', 'utf-8');
