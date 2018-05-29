<?php
/**
 * Updating Process
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */

class ProjectSendUpdate {

	function __construct( $attributes = array() ) {
		global $dbh;
		$this->dbh = $dbh;
	}

}
