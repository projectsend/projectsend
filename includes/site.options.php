<?php
/**
 * Gets all the options from the database and define each as a constant.
 *
 * @package		ProjectSend
 * @subpackage	Core
 *
 */
$database->MySQLDB();

/**
 * Gets the values from the options table, which has 2 columns.
 * The first one is the option name, and the second is the assigned value.
 *
 * @return array
 */
$options_values = array();
$options = $database->query("SELECT * FROM tbl_options");
if (mysql_num_rows($options) > 0) {
	while ($row = mysql_fetch_array($options)) {
		$options_values[$row['name']] = $row['value'];
	}
}
$database->Close();

/**
 * Set the options returned before as constants.
 */
if(!empty($options_values)) {
	/**
	 * The allowed file types array is set as variable and not a constant
	 * because it is re-set later on other pages (the options and the upload
	 * forms currently).
	 */
	$allowed_file_types = $options_values['allowed_file_types'];
	
	define('BASE_URI',$options_values['base_uri']);
	define('THUMBS_MAX_WIDTH',$options_values['max_thumbnail_width']);
	define('THUMBS_MAX_HEIGHT',$options_values['max_thumbnail_height']);
	define('THUMBS_FOLDER',$options_values['thumbnails_folder']);
	define('THUMBS_QUALITY',$options_values['thumbnail_default_quality']);
	define('THUMBS_USE_ABSOLUTE',$options_values['thumbnails_use_absolute']);
	define('LOGO_MAX_WIDTH',$options_values['max_logo_width']);
	define('LOGO_MAX_HEIGHT',$options_values['max_logo_height']);
	define('LOGO_FILENAME',$options_values['logo_filename']);
	define('THIS_INSTALL_SET_TITLE',$options_values['this_install_title']);
	define('TEMPLATE_USE',$options_values['selected_clients_template']);
	define('TIMEZONE_USE',$options_values['timezone']);
	define('TIMEFORMAT_USE',$options_values['timeformat']);
	define('CLIENTS_CAN_REGISTER',$options_values['clients_can_register']);
	/** Define the template path */
	define('TEMPLATE_PATH',ROOT_DIR.'/templates/'.TEMPLATE_USE.'/template.php');
	/**
	 * Wrap the e-mail definition in an IF statement in case the user 
	 * just updated to r135 and this value doesn't exist yet to prevent
	 * a php notice.
	 */	
	if (isset($options_values['admin_email_address'])) {
		define('ADMIN_EMAIL_ADDRESS',$options_values['admin_email_address']);
	}
	/**
	 * For versions 282 and up
	 */	
	if (isset($options_values['mail_system_use'])) {
		define('MAIL_SYSTEM',$options_values['mail_system_use']);
		define('SMTP_HOST',$options_values['mail_smtp_host']);
		define('SMTP_PORT',$options_values['mail_smtp_port']);
		define('SMTP_USER',$options_values['mail_smtp_user']);
		define('SMTP_PASS',$options_values['mail_smtp_pass']);
		define('MAIL_FROM_NAME',$options_values['mail_from_name']);
	}
	/**
	 * For versions 364 and up
	 */	
	if (isset($options_values['mail_copy_user_upload'])) {
		define('COPY_MAIL_ON_USER_UPLOADS',$options_values['mail_copy_user_upload']);
		define('COPY_MAIL_ON_CLIENT_UPLOADS',$options_values['mail_copy_client_upload']);
		define('COPY_MAIL_MAIN_USER',$options_values['mail_copy_main_user']);
		define('COPY_MAIL_ADDRESSES',$options_values['mail_copy_addresses']);
	}
	/**
	 * For versions 377 and up
	 */	
	if (isset($options_values['version_last_check'])) {
		define('VERSION_LAST_CHECK',$options_values['version_last_check']);
		define('VERSION_NEW_FOUND',$options_values['version_new_found']);
		if (VERSION_NEW_FOUND == '1') {
			define('VERSION_NEW_NUMBER',$options_values['version_new_number']);
			define('VERSION_NEW_URL',$options_values['version_new_url']);
			define('VERSION_NEW_CHLOG',$options_values['version_new_chlog']);
			define('VERSION_NEW_SECURITY',$options_values['version_new_security']);
			define('VERSION_NEW_FEATURES',$options_values['version_new_features']);
			define('VERSION_NEW_IMPORTANT',$options_values['version_new_important']);
		}
	}
	/**
	 * For versions 386 and up
	 */	
	if (isset($options_values['clients_auto_approve'])) {
		define('CLIENTS_AUTO_APPROVE',$options_values['clients_auto_approve']);
		define('CLIENTS_AUTO_GROUP',$options_values['clients_auto_group']);
		define('CLIENTS_CAN_UPLOAD',$options_values['clients_can_upload']);
	}

	/**
	 * For versions 419 and up
	 */	
	if (isset($options_values['email_new_file_by_user_customize'])) {
		/** Checkboxes */
		define('EMAILS_FILE_BY_USER_USE_CUSTOM',$options_values['email_new_file_by_user_customize']);
		define('EMAILS_FILE_BY_CLIENT_USE_CUSTOM',$options_values['email_new_file_by_client_customize']);
		define('EMAILS_CLIENT_BY_USER_USE_CUSTOM',$options_values['email_new_client_by_user_customize']);
		define('EMAILS_CLIENT_BY_SELF_USE_CUSTOM',$options_values['email_new_client_by_self_customize']);
		define('EMAILS_NEW_USER_USE_CUSTOM',$options_values['email_new_user_customize']);
		/** Texts */
		define('EMAILS_FILE_BY_USER_TEXT',$options_values['email_new_file_by_user_text']);
		define('EMAILS_FILE_BY_CLIENT_TEXT',$options_values['email_new_file_by_client_text']);
		define('EMAILS_CLIENT_BY_USER_TEXT',$options_values['email_new_client_by_user_text']);
		define('EMAILS_CLIENT_BY_SELF_TEXT',$options_values['email_new_client_by_self_text']);
		define('EMAILS_NEW_USER_TEXT',$options_values['email_new_user_text']);
	}

	/**
	 * For versions 426 and up
	 */	
	if (isset($options_values['email_header_footer_customize'])) {
		/** Checkbox */
		define('EMAILS_HEADER_FOOTER_CUSTOM',$options_values['email_header_footer_customize']);
		/** Texts */
		define('EMAILS_HEADER_TEXT',$options_values['email_header_text']);
		define('EMAILS_FOOTER_TEXT',$options_values['email_footer_text']);
	}

	/**
	 * For versions 442 and up
	 */	
	if (isset($options_values['email_pass_reset_customize'])) {
		/** Checkbox */
		define('EMAILS_PASS_RESET_USE_CUSTOM',$options_values['email_pass_reset_customize']);
		/** Text */
		define('EMAILS_PASS_RESET_TEXT',$options_values['email_pass_reset_text']);
	}

	/**
	 * For versions 464 and up
	 */	
	if (isset($options_values['expired_files_hide'])) {
		define('EXPIRED_FILES_HIDE',$options_values['expired_files_hide']);
	}

	/**
	 * For versions 487 and up
	 */	
	if (isset($options_values['notifications_max_tries'])) {
		define('NOTIFICATIONS_MAX_TRIES',$options_values['notifications_max_tries']);
		define('NOTIFICATIONS_MAX_DAYS',$options_values['notifications_max_days']);
	}
	else {
		define('NOTIFICATIONS_MAX_TRIES','2');
		define('NOTIFICATIONS_MAX_DAYS','15');
	}

	/**
	 * For versions 528 and up
	 */	
	if (isset($options_values['file_types_limit_to'])) {
		define('FILE_TYPES_LIMIT_TO',$options_values['file_types_limit_to']);
		define('PASS_REQ_UPPER',$options_values['pass_require_upper']);
		define('PASS_REQ_LOWER',$options_values['pass_require_lower']);
		define('PASS_REQ_NUMBER',$options_values['pass_require_number']);
		define('PASS_REQ_SPECIAL',$options_values['pass_require_special']);
		define('SMTP_AUTH',$options_values['mail_smtp_auth']);
	}

	/**
	 * Set the default timezone based on the value of the Timezone select box
	 * of the options page.
	 */
	date_default_timezone_set(TIMEZONE_USE);
} else {
    define('BASE_URI', '/');
}

/**
 * Timthumb
 */
if (defined('BASE_URI')) {
	define('TIMTHUMB_URL',BASE_URI.'includes/timthumb/timthumb.php');
	define('TIMTHUMB_ABS',ROOT_DIR.'/includes/timthumb/timthumb.php');
}

/**
 * Footable
 * Define the amount of items to show
 * TODO: Get this value of a cookie if it exists.
 */
if (!defined('FOOTABLE_PAGING_NUMBER')) {
	define('FOOTABLE_PAGING_NUMBER', '10');
	define('FOOTABLE_PAGING_NUMBER_LOG', '15');
}
?>