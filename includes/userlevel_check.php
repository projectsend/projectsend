<?php
/**
 * Contains all the functions used to validate the current logged in
 * client or user.
 *
 * @package ProjectSend
 *
 */

/**
 * Used on header.php to check if there is an active session or valid
 * cookie before generating the content.
 * If none is found, redirect to the log in form.
 */
function check_for_session( $redirect = true )
{
	$is_logged_now = false;
	if (isset($_SESSION['loggedin'])) {
		$is_logged_now = true;
	}
	elseif (isset($_SESSION['access']) && $_SESSION['access'] == 'admin') {
		$is_logged_now = true;
	}
	if ( !$is_logged_now && $redirect == true ) {
		header("location:" . BASE_URI . "index.php");
	}
	return $is_logged_now;
}

/**
 * Used on header.php to check if the current logged in account is either
 * a system user or a client.
 *
 * Clients are then redirected to the index page, where another check is
 * performed and then a second redirection takes the client to the
 * correspondent file list.
 *
 * @see check_for_client
 */
function check_for_admin() {
	$is_logged_admin = false;
	if (isset($_SESSION['access']) && $_SESSION['access'] == 'admin') {
		$is_logged_admin = true;
	}
	if (!$is_logged_admin) {
	    ob_clean();
		header("location:" . BASE_URI . "index.php");
	}
    return $is_logged_admin;
}

/**
 * Used on the log in form page (index.php) to take the clients directly to their
 * files list.
 * Also used on the self-registration form (register.php).
 */
function check_for_client() {
	if (isset($_SESSION['userlevel']) && $_SESSION['userlevel'] == '0') {
		header("location:" . CLIENT_VIEW_FILE_LIST_URL);
		exit;
	}
}

/**
 * Used on header.php to check if the current logged in system user has the
 * permission to view this page.
 */
function can_see_content($allowed_levels) {
	$permission = false;
	if(isset($allowed_levels)) {
		/**
		 * Check for a session, and if found see if the user
		 * level is among those defined by the page.
		 *
		 * $allowed_levels in defined on each page before the inclusion of header.php
		*/
		if (isset($_SESSION['userlevel']) && in_array($_SESSION['userlevel'],$allowed_levels)) {
			$permission = true;
		}
		/**
		 * After the checks, if the user is allowed, continue.
		 * If not, show the "Not allowed message", then the footer, then die(); so the
		 * actual page content is not generated.
		*/
	}
	if (!$permission) {
		header("location:".PAGE_STATUS_CODE_URL);
		exit;
    }
}