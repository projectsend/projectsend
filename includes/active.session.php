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

/**
 * Global information on the current account to use accross the system.
 */
$global_user = get_current_user_username();
$global_level = get_current_user_level();

/**
 * Get the user information from the database
 */
if ($global_level != 0) {
	$global_account = get_user_by_username($global_user);
}
else {
	$global_account = get_client_by_username($global_user);
}

/**
 * Automatic log out if account is deactivated while session is on.
 */
if ($global_account['active'] == '0') {
	/** Prevent an infinite loop */
	if (!isset($_SESSION['logout'])) {
		$_SESSION['logout'] = '1';
	}
	else {
		unset($_SESSION['logout']);
		header("location:".BASE_URI.'process.php?do=logout');
		exit;
	}
}

/**
 * Save all the data on different constants
 */
define('CURRENT_USER_ID',$global_account['id']);
define('CURRENT_USER_USERNAME',$global_account['username']);
define('CURRENT_USER_NAME',$global_account['name']);
define('CURRENT_USER_EMAIL',$global_account['email']);
define('CURRENT_USER_LEVEL',$global_account['level']);

$global_id = $global_account['id'];
$global_name = $global_account['name'];

/**
 * Check if account has a custom value for upload max file size
 */
if ( $global_account['max_file_size'] == 0 || empty( $global_account['max_file_size'] ) ) {
	define('UPLOAD_MAX_FILESIZE', MAX_FILESIZE);
}
else {
	define('UPLOAD_MAX_FILESIZE', $global_account['max_file_size']);
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
?>