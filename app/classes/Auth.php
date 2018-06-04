<?php
/**
 * Authentication (placeholder)
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

namespace ProjectSend;

class Auth {

	function __construct( $attributes = array() ) {
		global $auth;
		global $dbh;
		$this->dbh = $dbh;
	}

	/**
	 * Login
	 */
	public function login() {
	}

	/**
	 * Logout
	 */
	public function logout() {
	}
}
