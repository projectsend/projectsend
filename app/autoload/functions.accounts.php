<?php
/**
 * Function used accross the system to determine if the current logged in
 * account has permission to do something.
 *
 */
function in_session_or_cookies($levels)
{
	if (isset($_SESSION['userlevel']) && (in_array($_SESSION['userlevel'],$levels))) {
		return true;
	}
	/**
	 * Cookies are no longer used this way.
	 * userlevel_check.php has the answer.
	 */
	/*
	else if (isset($_COOKIE['userlevel']) && (in_array($_COOKIE['userlevel'],$levels))) {
		return true;
	}
	*/
	else {
		return false;
	}
}

/**
 * Returns the current logged in account level either from the active
 * session or the cookies.
 *
 * @todo Validate the returned value against the one stored on the database
 */
function get_current_user_level()
{
	$level = 0;
	if (isset($_SESSION['userlevel'])) {
		$level = $_SESSION['userlevel'];
	}
	/*
	elseif (isset($_COOKIE['userlevel'])) {
		$level = $_COOKIE['userlevel'];
	}
	*/
	return $level;
}

/**
 * Returns the current logged in account username either from the active
 * session or the cookies.
 *
 * @todo Validate the returned value against the one stored on the database
 */
function get_current_user_username()
{
	$user = '';
	/*
	if (isset($_COOKIE['loggedin'])) {
		$user = $_COOKIE['loggedin'];
	}
	*/
	/*else*/
	if (isset($_SESSION['loggedin'])) {
		$user = $_SESSION['loggedin'];
	}
	return $user;
}

/**
 * Get all the client information knowing only the log in username
 *
 * @return array
 */
function get_logged_account_id($username)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE username=:user");
	$statement->execute(
						array(
							':user'	=> $username
						)
					);
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$return_id = html_output($row['id']);
		if ( !empty( $return_id ) ) {
			return $return_id;
		}
		else {
			return false;
		}
	}
}

/**
 * @uses random_compat library, a polyfill for PHP 7's random_bytes();
 * @link: https://github.com/paragonie/random_compat
 */
function generate_password()
{
	$error_unexpected	= __('An unexpected error has occurred', 'cftp_admin');
	$error_os_fail		= __('Could not generate a random password', 'cftp_admin');

	try {
		$password = random_bytes(12);
	} catch (TypeError $e) {
		die($error_unexpected);
	} catch (Error $e) {
		die($error_unexpected);
	} catch (Exception $e) {
		die($error_os_fail);
	}

	return bin2hex($password);
}
