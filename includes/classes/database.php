<?php
/**
 * Simple database connection and query class.
 * Uses the information defined on sys.config.php.
 *
 * @link http://www.evolt.org/PHP-Login-System-with-Admin-Features
 * @package		ProjectSend
 * @subpackage	Classes
 *
 */

class MySQLDB
{
	/** The MySQL database connection */
	var $connection;

	/** Class constructor */
	function MySQLDB()
	{
		/** Make connection to database */
		$this->connection = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD) or die(mysql_error());
		mysql_select_db(DB_NAME, $this->connection) or die(mysql_error());
		//mysql_query("SET NAMES utf8");
	}
	
	/**
	* query - Performs the given query on the database and
	* returns the result, which may be false, true or a
	* resource identifier.
	*/
	function query($query)
	{
		$a = mysql_query($query, $this->connection);
		//echo mysql_error();
		return $a;
	}
	
	function Close()
	{
		mysql_close($this->connection);
	}
}

/** Create database connection */
$database = new MySQLDB;

?>