<?php
/**
 * Main App class
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

namespace ProjectSend;

class ProjectSend {

	function __construct( $attributes = array() ) {
		global $dbh;
		$this->dbh = $dbh;
	}

}
