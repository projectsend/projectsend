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
	header("Location:install/index.php");
	exit;
}

if ( defined('SESSION_TIMEOUT_EXPIRE') && SESSION_TIMEOUT_EXPIRE == true ) {
	if (isset($_SESSION['last_call']) && (time() - $_SESSION['last_call'] > SESSION_EXPIRE_TIME)) {
		header('Location: ' . BASE_URI . 'process.php?do=logout&timeout=1');
		exit;
	}
}
$_SESSION['last_call'] = time(); // update last activity time stamp

/**
 * Global information on the current account to use accross the system.
 */
if (!empty($_SESSION['user_id'])) {
    $session_user = new \ProjectSend\Classes\Users;
    $session_user->get($_SESSION['user_id']);
    if ($session_user->userExists()) {
        /**
         * Automatic log out if account is deactivated while session is on.
         */
        if (!$session_user->isActive()) {
            forceLogout('account_inactive');
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
        forceLogout();
    }
}

/**
 * Files types limitation
 */
$limit_files = true;
if ( defined( 'FILE_TYPES_LIMIT_TO' ) ) {
	switch ( FILE_TYPES_LIMIT_TO ) {
		case 'noone':
			$limit_files = false;
			break;
		case 'all':
			break;
		case 'clients':
			if ( CURRENT_USER_LEVEL != 0 ) {
				$limit_files = false;
			}
			break;
	}
}
if ( $limit_files === true ) {
	define('CAN_UPLOAD_ANY_FILE_TYPE', false);
}
else {
	define('CAN_UPLOAD_ANY_FILE_TYPE', true);
}