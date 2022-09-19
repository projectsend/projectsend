<?php
/**
 * Contains all the functions used to validate the current logged in
 * client or user.
 *
 * @package ProjectSend
 *
 */

 function extend_session()
{
    $_SESSION['last_call'] = time();
}

function session_expired()
{
    if ( defined('SESSION_TIMEOUT_EXPIRE') && SESSION_TIMEOUT_EXPIRE == true ) {
        if (isset($_SESSION['last_call']) && (time() - $_SESSION['last_call'] > SESSION_EXPIRE_TIME)) {
            return true;
        }
    }

    return false;
}

/**
 * Used on header.php to check if there is an active session or valid
 * cookie before generating the content.
 * If none is found, redirect to the log in form.
 */
function redirect_if_not_logged_in()
{
    $redirect = false;
    if (!user_is_logged_in()) {
        $redirect = true;
    } else {
        if (isset($_SESSION['user_id'])) {
            $user = new \ProjectSend\Classes\Users();
            if (!$user->get($_SESSION['user_id'])) {
                $redirect = true;
            }
        }
    }

    if ($redirect) {
        $_SESSION = [];
        session_destroy();
        ps_redirect(BASE_URI . "index.php");
    }
}

function user_is_logged_in()
{
    if (isset($_SESSION['user_id'])) {
        $user = new \ProjectSend\Classes\Users();
        if ($user->get($_SESSION['user_id'])) {
            return true;
        }
    }

    return false;
}

/**
 * Used on header.php to check if the current logged in system user has the
 * permission to view this page.
 */
function redirect_if_role_not_allowed($allowed_levels = null) {
	$permission = false;

    if (!empty($allowed_levels)) {
		/**
		 * Check for a session, and if found see if the user
		 * level is among those defined by the page.
		 *
		 * $allowed_levels in defined on each page before the inclusion of header.php
        */
        if (user_is_logged_in()) {
            $user = new \ProjectSend\Classes\Users();
            $user->get($_SESSION['user_id']);
            $user_data = $user->getProperties();

            if (isset($user_data['role']) && in_array($user_data['role'], $allowed_levels)) {
                $permission = true;
            }
        }
		/**
		 * After the checks, if the user is allowed, continue.
		 * If not, show the "Not allowed message", then the footer, then die(); so the
		 * actual page content is not generated.
		*/
    }

    if ($permission != true) {
        exit_with_error_code(403);
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


function user_can_upload_any_file_type($user_id = CURRENT_USER_ID)
{
    $user = new \ProjectSend\Classes\Users;
    $user->get($user_id);
    $properties = $user->getProperties();

    if (!empty(get_option('file_types_limit_to'))) {
        switch ( get_option('file_types_limit_to') ) {
            case 'noone':
                return true;
            break;
            case 'all':
                return false;
            break;
            case 'clients':
                if ($properties['role'] == 0) {
                    return false;
                }
            break;
        }
    }
    unset($user); unset($properties);
    
    return true;
}