<?php
/** Connect to the DB */
global $dbh;
if ( defined('DB_NAME') ) {
    try {
        switch ( DB_DRIVER ) {
            default:
            case 'mysql':
                $dbh = new \ProjectSend\Database\PDOExtended("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
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