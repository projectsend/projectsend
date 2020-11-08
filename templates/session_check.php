<?php
/**
 * Check for access permission to the files list.
 *
 * @package		ProjectSend
 * @subpackage	Templates
 */

/**
 * Check the "access" session var or cookie that are set on the index.php file
 * when you log in correctly.
 */
if (isset($_SESSION['access']) && $_SESSION['access'] == 'admin') { $grant_access = 1; }
if (isset($_SESSION['access']) && $_SESSION['access'] == CURRENT_USER_USERNAME) { $grant_access = 1; $is_client = 1; }

/** In case a client has a session or cookie but is deactivated */
if (isset($is_client)) {
    $client = get_client_by_username(CURRENT_USER_USERNAME);

	if ( $client['active'] == '0' ) {
		header("location:".BASE_URI.'process.php?do=logout');
		exit;
	}
}

/** If the info is not found, redirect to the log in page. */
if (!isset($grant_access)) {
	header("location:".BASE_URI);
	exit;
}