<?php
/**
 * Simple database connection and query class.
 * Uses the information defined on sys.config.php.
 */
use ProjectSend\Classes\Session;

/** Initiate the database connection */
if ( defined('DB_NAME') ) {
	global $dbh;
	try {
		switch ( DB_DRIVER ) {
			default:
			case 'mysql':
                $dbh = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            break;

			case 'mssql':
                $dbh = new PDO("mssql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            break;
		}

		$dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$dbh->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
	}
	catch(PDOException $e) {
        echo $e->getMessage();
        exit;
	}
}
