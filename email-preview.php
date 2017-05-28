<?php
/**
 * Show a preview of the currently selected e-mail template
 *
 * @package ProjectSend
 * @subpackage Options
 */
$allowed_levels = array(9);
require_once('sys.includes.php');

$page_title = __('E-mail templates','cftp_admin') . ': ' . __('Preview','cftp_admin');

$active_nav = 'options';

/** Do a couple of functions that are in header.php */
/** Check for an active session or cookie */
check_for_session();

can_see_content($allowed_levels);

/** Get the default header and footer */
global $email_template_header;
global $email_template_footer;

/** Get the preview type */
$type = $_GET['t'];

switch ($type) {
	case 'client_by_user':
			$subject	= EMAILS_CLIENT_BY_USER_SUBJECT;
			$body_text	= EMAILS_CLIENT_BY_USER_TEXT;
		break;
	case 'client_by_self':
			$subject	= EMAILS_CLIENT_BY_SELF_SUBJECT;
			$body_text	= EMAILS_CLIENT_BY_SELF_TEXT;
		break;
	case 'new_user_welcome':
			$subject	= EMAILS_NEW_USER_SUBJECT;
			$body_text	= EMAILS_NEW_USER_TEXT;
		break;
	case 'file_by_user':
			$subject	= EMAILS_FILE_BY_USER_SUBJECT;
			$body_text	= EMAILS_FILE_BY_USER_TEXT;
		break;
	case 'file_by_client':
			$subject	= EMAILS_FILE_BY_CLIENT_SUBJECT;
			$body_text	= EMAILS_FILE_BY_CLIENT_TEXT;
		break;
	case 'password_reset':
			$subject	= EMAILS_PASS_RESET_SUBJECT;
			$body_text	= EMAILS_PASS_RESET_TEXT;
		break;
}

/**
 * Header and footer
 */
if (!defined('EMAILS_HEADER_FOOTER_CUSTOM') || EMAILS_HEADER_FOOTER_CUSTOM == '0') {
	$header = $email_template_header;
	$footer = $email_template_footer;
}
else {
	$header = EMAILS_HEADER_TEXT;
	$footer = EMAILS_FOOTER_TEXT;
}

echo $header . $body_text . $footer;

ob_end_flush();
?>