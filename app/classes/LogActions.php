<?php
/**
 * Class that handles all the actions that are logged on the database.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 *
 */

namespace ProjectSend;
use PDO;

class LogActions
{

	var $action = '';

	/**
	 * Create a new client.
	 */
	function add_entry($arguments)
	{
		global $dbh;
		$this->state = array();

		/** Define the account information */
		$this->action = $arguments['action'];
		$this->owner_id = $arguments['owner_id'];
		$this->owner_user = (!empty($arguments['owner_user'])) ? $arguments['owner_user'] : CURRENT_USER_NAME;
		$this->affected_file = (!empty($arguments['affected_file'])) ? $arguments['affected_file'] : '';
		$this->affected_account = (!empty($arguments['affected_account'])) ? $arguments['affected_account'] : '';
		$this->affected_file_name = (!empty($arguments['affected_file_name'])) ? $arguments['affected_file_name'] : '';
		$this->affected_account_name = (!empty($arguments['affected_account_name'])) ? $arguments['affected_account_name'] : '';
		
		/** Get the real name of the client or user */
		if (!empty($arguments['get_user_real_name'])) {
			$this->short_query = $dbh->prepare( "SELECT name FROM " . TABLE_USERS . " WHERE username =:user" );
			$params = array(
							':user'		=> $this->affected_account_name,
						);
			$this->short_query->execute( $params );
			$this->short_query->setFetchMode(PDO::FETCH_ASSOC);
			while ( $srow = $this->short_query->fetch() ) {
				$this->affected_account_name = $srow['name'];
			}
		}

		/** Get the title of the file on downloads */
		if (!empty($arguments['get_file_real_name'])) {
			$this->short_query = $dbh->prepare( "SELECT filename FROM " . TABLE_FILES . " WHERE url =:file" );
			$params = array(
							':file'		=> $this->affected_file_name,
						);
			$this->short_query->execute( $params );
			$this->short_query->setFetchMode(PDO::FETCH_ASSOC);
			while ( $srow = $this->short_query->fetch() ) {
				$this->affected_file_name = $srow['filename'];
			}
		}

		/** Insert the client information into the database */
		$lq = "INSERT INTO " . TABLE_ACTIONS_LOG . " (action,owner_id,owner_user";
		
			if (!empty($this->affected_file)) { $lq .= ",affected_file"; }
			if (!empty($this->affected_account)) { $lq .= ",affected_account"; }
			if (!empty($this->affected_file_name)) { $lq .= ",affected_file_name"; }
			if (!empty($this->affected_account_name)) { $lq .= ",affected_account_name"; }
		
		$lq .= ") VALUES (:action, :owner_id, :owner_user";

			$params = array(
							':action'		=> $this->action,
							':owner_id'		=> $this->owner_id,
							':owner_user'	=> $this->owner_user,
						);
		
			if (!empty($this->affected_file)) {			$lq .= ", :file";		$params['file'] = $this->affected_file; }
			if (!empty($this->affected_account)) {		$lq .= ", :account";	$params['account'] = $this->affected_account; }
			if (!empty($this->affected_file_name)) {	$lq .= ", :title";		$params['title'] = $this->affected_file_name; }
			if (!empty($this->affected_account_name)) {	$lq .= ", :name";		$params['name'] = $this->affected_account_name; }

		$lq .= ")";

		$this->sql_query = $dbh->prepare( $lq );
		$this->sql_query->execute( $params );
	}
}