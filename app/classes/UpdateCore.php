<?php
/**
 * Updating Process
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */

namespace ProjectSend;

class UpdateCore {

	function __construct( $attributes = array() ) {
		global $dbh;
		$this->dbh = $dbh;
	}

}
