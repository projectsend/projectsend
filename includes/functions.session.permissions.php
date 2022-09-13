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

    if (isset($_SESSION['user_id'])) {
        $user = new \ProjectSend\Classes\Users();
        if (!$user->get($_SESSION['user_id'])) {
            $_SESSION = [];
            session_destroy();
            ps_redirect(BASE_URI . "index.php");
        }

        return true;
    }

    if ( !$is_logged_now && $redirect == true ) {
        ps_redirect(BASE_URI . "index.php");
	}

    return $is_logged_now;
}

/**
 * Used on header.php to check if the current logged in system user has the
 * permission to view this page.
 */
function can_see_content($allowed_levels = null) {
	$permission = false;

    if (!empty($allowed_levels)) {
		/**
		 * Check for a session, and if found see if the user
		 * level is among those defined by the page.
		 *
		 * $allowed_levels in defined on each page before the inclusion of header.php
        */
		if (isset($_SESSION['role']) && in_array($_SESSION['role'], $allowed_levels)) {
			$permission = true;
		}
		/**
		 * After the checks, if the user is allowed, continue.
		 * If not, show the "Not allowed message", then the footer, then die(); so the
		 * actual page content is not generated.
		*/
    }

    if ($permission == false) {
        ps_redirect(PAGE_STATUS_CODE_URL);
    }
}

// Requires password change?
function password_change_required()
{
    global $flash;
    $session_user = new \ProjectSend\Classes\Users;
    $session_user->get(CURRENT_USER_ID);

    if ($session_user->requiresPasswordChange()) {
        $url = (CURRENT_USER_LEVEL == 0) ? 'clients-edit.php' : 'users-edit.php';
        if (basename($_SERVER["SCRIPT_FILENAME"]) != $url) {
            $flash->warning(__('Password change is required for your account', 'cftp_admin'));

            $url .= '?id='.CURRENT_USER_ID;
            ps_redirect(BASE_URI.$url);
        }
    }
}
