<?php
/**
 * Main App class
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class ProjectSend {

	function __construct( $attributes = array() ) {
		global $dbh;
		$this->dbh = $dbh;
	}

	/**
	 * Gets a template file
	 */
	public function get_part() {
	}

	/**
	 * Outputs the whole buffer and ends execution
	 */
	public function render() {
	}
}
