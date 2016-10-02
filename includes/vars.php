<?php
/**
 * Define the language strings that are used on several parts of
 * the system, to avoid repetition.
 *
 * @package		ProjectSend
 * @subpackage	Core
 */

/**
 * System User Roles names
 */
$user_role_9_name = __('System Administrator','cftp_admin');
$user_role_8_name = __('Account Manager','cftp_admin');
$user_role_7_name = __('Uploader','cftp_admin');
$user_role_0_name = __('Client','cftp_admin');
if ( !defined( 'USER_ROLE_LVL_9' ) ) { define('USER_ROLE_LVL_9', $user_role_9_name); }
if ( !defined( 'USER_ROLE_LVL_8' ) ) { define('USER_ROLE_LVL_8', $user_role_8_name); }
if ( !defined( 'USER_ROLE_LVL_7' ) ) { define('USER_ROLE_LVL_7', $user_role_7_name); }
if ( !defined( 'USER_ROLE_LVL_0' ) ) { define('USER_ROLE_LVL_0', $user_role_0_name); }

/**
 * Validation class strings
 */
$validation_recaptcha		= __('reCAPTCHA verification failed','cftp_admin');
$validation_no_name			= __('Name was not completed','cftp_admin');
$validation_no_client		= __('No client was selected','cftp_admin');
$validation_no_user			= __('Username was not completed','cftp_admin');
$validation_no_pass			= __('Password was not completed','cftp_admin');
$validation_no_pass2		= __('Password verification was not completed','cftp_admin');
$validation_no_email		= __('E-mail was not completed','cftp_admin');
$validation_invalid_mail	= __('E-mail address is not valid','cftp_admin');
$validation_alpha_user		= __('Username must be alphanumeric and may contain dot (a-z,A-Z,0-9 and . allowed)','cftp_admin');
$validation_alpha_pass		= __('Password must be alphanumeric (a-z,A-Z,0-9 allowed)','cftp_admin');
$validation_match_pass		= __('Passwords do not match','cftp_admin');
$validation_rules_pass		= __('Password does not meet the required characters rules','cftp_admin');
$validation_no_level		= __('User level was not specified','cftp_admin');
$add_user_exists			= __('A system user or client with this login name already exists.','cftp_admin');
$add_user_mail_exists		= __('A system user or client with this e-mail address already exists.','cftp_admin');
$validation_valid_pass		= __('Your password can only contain letters, numbers and the following characters:','cftp_admin');
$validation_valid_chars		= ('` ! " ? $ ? % ^ & * ( ) _ - + = { [ } ] : ; @ ~ # | < , > . ? \' / \ ');
$validation_no_title		= __('Title was not completed','cftp_admin');

/**
 * Validation strings for the length of usernames and passwords.
 * Uses the MIN and MAX values defined on sys.vars.php
 */
$validation_length_usr_1 = __('Username','cftp_admin');
$validation_length_pass_1 = __('Password','cftp_admin');
$validation_length_1 = __('length should be between','cftp_admin');
$validation_length_2 = __('and','cftp_admin');
$validation_length_3 = __('characters long','cftp_admin');
$validation_length_user = $validation_length_usr_1.' '.$validation_length_1.' '.MIN_USER_CHARS.' '.$validation_length_2.' '.MAX_USER_CHARS.' '.$validation_length_3;
$validation_length_pass = $validation_length_pass_1.' '.$validation_length_1.' '.MIN_PASS_CHARS.' '.$validation_length_2.' '.MAX_PASS_CHARS.' '.$validation_length_3;

$validation_req_upper	= __('1 uppercase character','cftp_admin');
$validation_req_lower	= __('1 lowercase character','cftp_admin');
$validation_req_number	= __('1 number','cftp_admin');
$validation_req_special	= __('1 special character','cftp_admin');
?>