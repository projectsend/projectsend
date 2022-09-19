<?php
/**
 * Define the information about the current logged in user or client
 * used on the different validations across the system.
 *
 * @package		ProjectSend
 * @subpackage	Session
 */
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

/** Check for a complete installation */
if (!is_projectsend_installed()) {
    ps_redirect('install/index.php');
}

if ( session_expired() ) {
    ps_redirect(BASE_URI . 'process.php?do=logout&timeout=1');
}

extend_session(); // update last activity time stamp

/**
 * Global information on the current account to use across the system.
 */
if (!empty($_SESSION['user_id'])) {
    $session_user = new \ProjectSend\Classes\Users;
    $session_user->get($_SESSION['user_id']);
    if ($session_user->userExists()) {
        /**
         * Automatic log out if account is deactivated while session is on.
         */
        if (!$session_user->isActive()) {
            force_logout('account_inactive');
        }
    
        /**
         * Save all the data on different constants
         */
        define('CURRENT_USER_ID', $session_user->id);
        define('CURRENT_USER_USERNAME', $session_user->username);
        define('CURRENT_USER_NAME', $session_user->name);
        define('CURRENT_USER_EMAIL', $session_user->email);
        define('CURRENT_USER_LEVEL', $session_user->role);
        define('CURRENT_USER_TYPE', $session_user->account_type);
    
        // Check if account has a custom value for upload max file size
        if ( $session_user->max_file_size == 0 || empty( $session_user->max_file_size ) ) {
            define('UPLOAD_MAX_FILESIZE', (int)MAX_FILESIZE);
        }
        else {
            define('UPLOAD_MAX_FILESIZE', (int)$session_user->max_file_size);
        }
    } else {
        force_logout();
    }
}
