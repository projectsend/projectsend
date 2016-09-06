<?php
/**
 * Simple database connection and query class.
 * Uses the information defined on sys.config.php.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 *
 */

/** Extension class to count the total of executed queries */
class PDOEx extends PDO
{
	private $queries = 0;
	
	public function query($query) {
		++$this->queries;
		return parent::query($query);
	}

	public function prepare($statement) {
		++$this->queries;
		return parent::prepare($statement);
	}
	
	public function GetCount() {
		return $this->queries;
	}
}

/** Initiate the database connection */
global $dbh;
try {
	switch ( DB_DRIVER ) {
		default:
		case 'mysql':
			$dbh = new PDOEx("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
			break;

		case 'mssql':
			$dbh = new PDOEx("mssql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
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
?>